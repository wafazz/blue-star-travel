<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentCreateBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Package $package;
    private PackagePricing $twin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'open',
        ]);
        // Capacity 2, and the fixtures book exactly 2 — a room line has to be filled to
        // the occupancy the admin configured, which is what assertRoomCapacity() enforces.
        $this->twin = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Twin', 'capacity' => 2,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500,
            'infant_price' => 0, 'is_default' => true,
        ]);
    }

    /** `$overrides` goes first — PHP's `+` keeps the LEFT operand on a key collision. */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'package_id'  => $this->package->id,
            'type'        => 'online',
            'travel_date' => now()->addMonths(2)->format('Y-m-d'),
            'rooms' => [['package_pricing_id' => $this->twin->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'deposit_amount' => 1500,
            'deposit_method' => 'slip_upload',
            'deposit_slip'   => UploadedFile::fake()->image('deposit.jpg'),
        ];
    }

    /**
     * The "➕ New customer…" option posts `__new` rather than "" so Select2 keeps it in
     * the list — Select2 drops every empty-value option as a placeholder.
     */
    public function test_the_new_customer_sentinel_registers_a_customer_instead_of_failing_validation(): void
    {
        $response = $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id'        => '__new',
            'new_customer_name'  => 'Nur Aina Binti Rahim',
            'new_customer_phone' => '0123456789',
            'new_customer_email' => 'aina@example.com',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Nur Aina Binti Rahim')->first();
        $this->assertNotNull($customer, '`__new` must not reach the exists: rule.');
        $this->assertSame($this->agent->id, $customer->agent_id, 'A self-registered customer belongs to the agent.');
        $this->assertSame('0123456789', $customer->phone);

        $booking = Booking::first();
        $this->assertSame($customer->id, $booking->customer_id);
    }

    public function test_picking_an_existing_customer_still_works(): void
    {
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
        ]))->assertRedirect();

        $this->assertSame(1, Customer::count(), 'No duplicate customer may be created.');
        $this->assertSame($customer->id, Booking::first()->customer_id);
    }

    public function test_the_new_customer_option_is_offered_on_the_form(): void
    {
        Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('New customer', false)
            ->assertSee('value="__new"', false);
    }

    public function test_a_new_customer_still_needs_a_name_and_phone(): void
    {
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => '__new',
        ]))->assertSessionHasErrors('customer_id');

        $this->assertSame(0, Booking::count());
    }

    /** The client's three-item gate — the deposit half of it, enforced server-side. */
    public function test_a_booking_cannot_be_submitted_without_a_deposit_receipt_and_amount(): void
    {
        $base = $this->payload(['customer_id' => '__new', 'new_customer_name' => 'Aina', 'new_customer_phone' => '0123456789']);

        foreach (['deposit_slip', 'deposit_amount'] as $field) {
            $this->actingAs($this->agent)
                ->post(route('agent.bookings.store'), array_diff_key($base, [$field => null]))
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Booking::count(), 'Neither attempt may create a booking.');
    }

    /** Pack lines and a booked date are the other two — assertDateSelection() guards both. */
    public function test_a_booking_needs_pack_details_and_a_booked_date(): void
    {
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'rooms'       => [['package_pricing_id' => $this->twin->id, 'adults' => 0, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
        ]))->assertSessionHasErrors('rooms');

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'travel_date' => null,
        ]))->assertSessionHasErrors('travel_date');

        $this->assertSame(0, Booking::count());
    }

    /**
     * The client's rule: the tier's capacity is a CEILING. A 2-pax twin will not take 3.
     * It will take 1 — that is single occupancy, and they pay the twin rate for it.
     */
    public function test_a_room_cannot_hold_more_pax_than_its_tier_allows(): void
    {
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'rooms' => [['package_pricing_id' => $this->twin->id, 'adults' => 2, 'children' => 1, 'seniors' => 0, 'infants' => 0]],
        ]))->assertSessionHasErrors('rooms.0');

        $this->assertSame(0, Booking::count(), 'Over-filling may not create a booking.');
    }

    public function test_a_room_may_be_under_filled(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        // One traveller in a 2-pax twin — single occupancy is a normal booking.
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'rooms' => [['package_pricing_id' => $this->twin->id, 'adults' => 1, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, (int) Booking::first()->total_pax);
    }

    /** Infants share a bed, so they never count against the room's occupancy. */
    public function test_an_infant_does_not_count_against_room_capacity(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'rooms' => [['package_pricing_id' => $this->twin->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 1]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
    }

    /** A party bigger than one room adds a second line — each still filled exactly. */
    public function test_two_full_rooms_are_accepted(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'rooms' => [
                ['package_pricing_id' => $this->twin->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0],
                ['package_pricing_id' => $this->twin->id, 'adults' => 1, 'children' => 1, 'seniors' => 0, 'infants' => 0],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(4, (int) Booking::first()->total_pax);
    }

    public function test_the_form_asks_for_a_check_in_and_a_check_out_date(): void
    {
        $this->actingAs($this->agent)->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('Check-in Date')
            ->assertSee('Check-out Date')
            ->assertSee('name="return_date"', false)
            // Every field the agent must fill carries the marker.
            ->assertSee('<span class="req">*</span>', false);
    }

    public function test_the_check_out_date_is_stored_and_must_follow_the_check_in(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $checkIn = now()->addMonths(2);
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'travel_date' => $checkIn->format('Y-m-d'),
            'return_date' => $checkIn->copy()->addDays(3)->format('Y-m-d'),
        ]))->assertSessionHasNoErrors();

        $booking = Booking::first();
        $this->assertSame($checkIn->copy()->addDays(3)->format('Y-m-d'), $booking->return_date->format('Y-m-d'));
        $this->assertSame($checkIn->copy()->addDays(3)->format('Y-m-d'), $booking->returnDate()->format('Y-m-d'));
        $this->assertSame(3, $booking->nights());

        // A check-out on or before the check-in is nonsense.
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
            'travel_date' => $checkIn->format('Y-m-d'),
            'return_date' => $checkIn->copy()->subDay()->format('Y-m-d'),
        ]))->assertSessionHasErrors('return_date');
    }

    public function test_the_deposit_is_recorded_as_a_pending_payment_on_the_new_booking(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id'       => $customer->id,
            'deposit_amount'    => 2500,
            'deposit_reference' => 'TRX-99',
        ]))->assertRedirect();

        $payment = Payment::first();
        $this->assertNotNull($payment, 'The deposit must land as a payment row.');
        $this->assertSame(Booking::first()->id, $payment->booking_id);
        $this->assertEquals(2500, $payment->amount);
        $this->assertSame('deposit', $payment->type);
        $this->assertSame('pending', $payment->status, 'Staff still verify it.');
        $this->assertSame('TRX-99', $payment->reference);
        Storage::disk('local')->assertExists($payment->slip_path);
    }

    /**
     * Instalments are the norm now the agent types the amount, so a part-payment may not
     * be filed as `full`. It used to key off `paid_amount`, which is still 0 while the
     * deposit sits unverified — every instalment came out labelled "full".
     */
    public function test_a_part_payment_is_recorded_as_partial_not_full(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id, 'deposit_amount' => 1000,
        ]))->assertRedirect();

        $booking = Booking::first();
        $this->assertSame('deposit', $booking->payments()->first()->type);

        // Nothing is verified yet, so paid_amount is still 0 and the balance is the full trip.
        $this->actingAs($this->agent)->post(route('agent.bookings.payment', $booking), [
            'amount' => 500, 'method' => 'slip_upload', 'slip' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertRedirect();

        $this->assertSame('partial', $booking->payments()->latest('id')->first()->type);

        // One that clears the outstanding balance is a settlement, not an instalment.
        $this->actingAs($this->agent)->post(route('agent.bookings.payment', $booking), [
            'amount' => $booking->balance(), 'method' => 'slip_upload', 'slip' => UploadedFile::fake()->image('slip2.jpg'),
        ])->assertRedirect();

        $this->assertSame('full', $booking->payments()->latest('id')->first()->type);
    }

    /** The redesigned payment section: 4 tiles, history, note — and no FPX shortcut. */
    public function test_the_payment_section_shows_the_deposit_breakdown_and_no_fpx_button(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id, 'deposit_amount' => 600, 'deposit_reference' => 'Tukar Bilik',
        ]))->assertRedirect();

        $booking = Booking::first();
        $this->actingAs($this->agent)->post(route('agent.bookings.payment', $booking), [
            'amount' => 800, 'method' => 'cash', 'reference' => 'Office Payment',
            'slip' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertRedirect();

        $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Original Deposit Paid')
            ->assertSee('Tukar Bilik')
            ->assertSee('Add Deposit')
            ->assertSee('Deposit Summary')
            ->assertSee('Payment History')
            ->assertSee('Agent Note')
            ->assertSee('RM 600.00')   // original deposit
            ->assertSee('RM 800.00')   // additional deposits
            ->assertSee('RM 1,400.00') // total recorded
            ->assertSee('RM 15,600.00') // outstanding on a 17,000 trip
            ->assertSee('Refresh totals')
            ->assertDontSee('Pay Balance via FPX');
    }

    public function test_the_deposit_totals_ignore_a_rejected_slip(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id, 'deposit_amount' => 600,
        ]))->assertRedirect();

        $booking = Booking::first();
        $booking->payments()->create(['amount' => 900, 'method' => 'cash', 'type' => 'partial', 'status' => 'rejected']);

        $this->assertEquals(600, $booking->fresh()->recordedTotal(), 'A rejected slip is not money.');
        $this->assertEquals(0, $booking->fresh()->additionalDepositsTotal());
    }

    public function test_an_agent_saves_a_note_for_admin_without_touching_the_customer_requests(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id, 'notes' => 'Window seat please',
        ]))->assertRedirect();

        $booking = Booking::first();
        $this->actingAs($this->agent)->post(route('agent.bookings.note', $booking), [
            'agent_note' => 'Customer paying the rest by 30 Aug.',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('Customer paying the rest by 30 Aug.', $booking->agent_note);
        $this->assertSame('Window seat please', $booking->notes, 'The customer request must survive.');

        // Write-only would make the field pointless — staff have to actually see it.
        $staff = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($staff)->get(route('manage.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Customer paying the rest by 30 Aug.');
    }

    public function test_another_agent_cannot_write_a_note_on_someone_elses_booking(): void
    {
        Storage::fake('local');
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload(['customer_id' => $customer->id]));

        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($other)->post(route('agent.bookings.note', Booking::first()), ['agent_note' => 'mine now'])
            ->assertForbidden();
    }

    public function test_the_submit_button_starts_disabled_on_the_form(): void
    {
        $this->actingAs($this->agent)->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('id="submitBtn"', false)
            ->assertSee('disabled', false)
            ->assertSee('name="deposit_slip"', false);
    }

    public function test_an_agent_cannot_book_another_agents_customer(): void
    {
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $theirs = Customer::create(['name' => 'Not Mine', 'phone' => '0199999999', 'agent_id' => $other->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $theirs->id,
        ]))->assertForbidden();
    }
}
