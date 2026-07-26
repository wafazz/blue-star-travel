<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Package $package;
    private PackagePricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $provider = Provider::create(['name' => 'P', 'type' => 'local_operator', 'status' => 'active']);
        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'provider_id' => $provider->id, 'date_mode' => 'open',
        ]);
        $this->pricing = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0,
            'is_default' => true,
        ]);
    }

    private function booking(string $status = 'needs_revision'): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create([
            'name' => 'Nur Aina', 'phone' => '0123456789', 'email' => 'aina@example.com',
            'agent_id' => $this->agent->id,
        ]);

        $booking = Booking::create([
            'booking_no'  => "BK-DRAFT-{$n}",
            'package_id'  => $this->package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $this->agent->id,
            'status'      => $status,
            'travel_date' => '2026-09-14',
            'adults'      => 2,
            'total_pax'   => 2,
            'subtotal'    => 17000,
            'total_amount' => 17000,
        ]);
        $booking->rooms()->create([
            'package_pricing_id' => $this->pricing->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0,
            'subtotal' => 17000,
        ]);
        $booking->pax()->create(['name' => 'Nur Aina', 'type' => 'adult', 'is_lead' => true]);
        $booking->pax()->create(['name' => 'Aliya', 'type' => 'child', 'dob' => '2022-04-12']);

        return $booking;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'customer_name'   => 'Nur Aina',
            'customer_phone'  => '0123456789',
            'customer_email'  => 'aina@example.com',
            'customer_ic'     => '900101-10-5566',
            'package_id'      => $this->package->id,
            'travel_date'     => '2026-09-14',
            'pickup_location' => 'KLIA2 Gate C',
            'arrival_time'    => '06:30',
            'notes'           => 'Wheelchair needed',
            'rooms' => [['package_pricing_id' => $this->pricing->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'pax'   => [['key' => 'new-0', 'name' => 'Nur Aina', 'type' => 'adult']],
            'payment_amount' => '2000.00',
            'payment_method' => 'slip_upload',
        ];
    }

    private function saveDraft(Booking $booking, array $overrides = [])
    {
        return $this->actingAs($this->agent)
            ->post(route('agent.bookings.draft', $booking), $this->payload($overrides));
    }

    /** The load-bearing guarantee of the whole phase. */
    public function test_saving_a_draft_leaves_every_live_record_byte_identical(): void
    {
        $booking = $this->booking();

        $before = [
            'bookings'      => DB::table('bookings')->where('id', $booking->id)->get()->toJson(),
            'booking_rooms' => DB::table('booking_rooms')->where('booking_id', $booking->id)->get()->toJson(),
            'booking_pax'   => DB::table('booking_pax')->where('booking_id', $booking->id)->get()->toJson(),
            'customers'     => DB::table('customers')->where('id', $booking->customer_id)->get()->toJson(),
            'payments'      => DB::table('payments')->where('booking_id', $booking->id)->get()->toJson(),
        ];

        $this->saveDraft($booking, [
            'customer_name'   => 'COMPLETELY DIFFERENT',
            'customer_phone'  => '0199999999',
            'pickup_location' => 'Somewhere else',
            'notes'           => 'changed',
            'payment_amount'  => '9999.00',
            'rooms' => [['package_pricing_id' => $this->pricing->id, 'adults' => 4, 'children' => 3, 'seniors' => 1, 'infants' => 2]],
            'pax'   => [['key' => 'new-0', 'name' => 'Someone Else', 'type' => 'senior']],
        ])->assertRedirect();

        foreach ($before as $table => $json) {
            $after = DB::table($table === 'bookings' ? 'bookings' : $table)
                ->where($table === 'bookings' ? 'id' : ($table === 'customers' ? 'id' : 'booking_id'),
                    $table === 'customers' ? $booking->customer_id : $booking->id)
                ->get()->toJson();
            $this->assertSame($json, $after, "{$table} must be untouched by a draft save.");
        }

        $this->assertDatabaseCount('booking_drafts', 1);
    }

    public function test_the_draft_holds_what_the_agent_typed(): void
    {
        $booking = $this->booking();
        $this->saveDraft($booking, ['customer_name' => 'Edited Name', 'pickup_location' => 'KLIA1']);

        $draft = $booking->drafts()->first();
        $this->assertSame('Edited Name', $draft->value('customer.name'));
        $this->assertSame('KLIA1', $draft->value('booking.pickup_location'));
        $this->assertSame('06:30', $draft->value('booking.arrival_time'));
        $this->assertSame('2000.00', $draft->value('payment.amount'));
        $this->assertSame(2, $draft->value('rooms.0.adults'));
        $this->assertSame($this->agent->id, $draft->user_id);
    }

    public function test_the_edit_form_rehydrates_from_the_draft_after_signing_back_in(): void
    {
        $booking = $this->booking();
        $this->saveDraft($booking, ['customer_name' => 'Rehydrated Person', 'pickup_location' => 'Gate B7']);

        // New session — the form must come back from the draft, not the live record.
        auth()->logout();
        $response = $this->actingAs($this->agent)->get(route('agent.bookings.edit', $booking));

        $response->assertOk();
        $response->assertSee('Rehydrated Person', false);
        $response->assertSee('Gate B7', false);
        $response->assertSee('Draft saved');
    }

    public function test_saving_twice_updates_the_same_draft_row(): void
    {
        $booking = $this->booking();
        $this->saveDraft($booking, ['customer_name' => 'First']);
        $this->saveDraft($booking, ['customer_name' => 'Second']);

        $this->assertDatabaseCount('booking_drafts', 1);
        $this->assertSame('Second', $booking->drafts()->first()->value('customer.name'));
    }

    public function test_discarding_removes_the_draft_and_the_form_falls_back_to_live_data(): void
    {
        $booking = $this->booking();
        $this->saveDraft($booking, ['customer_name' => 'Throwaway']);

        $this->actingAs($this->agent)
            ->delete(route('agent.bookings.draft.discard', $booking))
            ->assertRedirect(route('agent.bookings.show', $booking));

        $this->assertDatabaseCount('booking_drafts', 0);
        $this->actingAs($this->agent)->get(route('agent.bookings.edit', $booking))->assertSee('Nur Aina', false);
    }

    /** No admin request needed — any live booking is editable, including a confirmed one. */
    public function test_a_live_booking_is_editable_without_an_admin_revision_request(): void
    {
        foreach (['draft', 'needs_revision', 'pending_payment', 'pending_verification', 'waiting_provider_confirmation', 'confirmed'] as $status) {
            $booking = $this->booking($status);
            $this->actingAs($this->agent)->get(route('agent.bookings.edit', $booking))
                ->assertOk("{$status} should be editable");
            $this->saveDraft($booking)->assertRedirect();
        }
    }

    public function test_a_finished_or_cancelled_booking_is_locked(): void
    {
        foreach (['completed', 'cancelled', 'rejected', 'refunded'] as $status) {
            $booking = $this->booking($status);
            $this->actingAs($this->agent)->get(route('agent.bookings.edit', $booking))
                ->assertForbidden("{$status} must be locked");
            $this->saveDraft($booking)->assertForbidden();
        }
    }

    public function test_another_agent_cannot_open_or_stage_an_edit(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($other)->get(route('agent.bookings.edit', $booking))->assertForbidden();
        $this->actingAs($other)->post(route('agent.bookings.draft', $booking), $this->payload())->assertForbidden();
        $this->actingAs($other)->get(route('agent.bookings.draft-slip', $booking))->assertForbidden();
    }

    public function test_a_replaced_receipt_is_staged_privately_and_the_old_payment_is_untouched(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $payment = Payment::create([
            'booking_id' => $booking->id, 'amount' => 2000, 'method' => 'slip_upload',
            'type' => 'deposit', 'status' => 'pending', 'slip_path' => 'payment-slips/original.jpg',
        ]);

        $this->saveDraft($booking, ['slip' => UploadedFile::fake()->image('new-receipt.jpg')]);

        $staged = $booking->drafts()->first()->value('payment.slip_path');
        $this->assertNotSame('payment-slips/original.jpg', $staged);
        Storage::disk('local')->assertExists($staged);
        // The live payment row — and the file an older version still links to — must survive.
        $this->assertSame('payment-slips/original.jpg', $payment->refresh()->slip_path);
    }

    public function test_the_draft_slip_route_serves_only_the_owning_agents_staged_file(): void
    {
        Storage::fake('local');
        $booking = $this->booking();

        $this->actingAs($this->agent)->get(route('agent.bookings.draft-slip', $booking))->assertNotFound();

        $this->saveDraft($booking, ['slip' => UploadedFile::fake()->image('r.jpg')]);
        $this->actingAs($this->agent)->get(route('agent.bookings.draft-slip', $booking))->assertOk();
    }

    public function test_requesting_a_revision_records_the_original_submission_as_v1(): void
    {
        $booking = $this->booking('pending_verification');

        app(BookingService::class)->requestRevision($booking, $this->admin, 'fix the child age', ['pax']);

        $versions = $booking->versions()->get();
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->version);
        $this->assertSame('initial', $versions[0]->reason);
        $this->assertSame('Nur Aina', data_get($versions[0]->payload, 'customer.name'));
        $this->assertSame('Umrah 12D', data_get($versions[0]->payload, 'booking.package_title'));
        $this->assertNull($versions[0]->changes, 'v1 has nothing to diff against.');
    }

    public function test_a_second_revision_round_does_not_duplicate_the_initial_version(): void
    {
        $booking = $this->booking('pending_verification');
        $service = app(BookingService::class);

        $service->requestRevision($booking, $this->admin, 'round one', ['pax']);
        $booking->refresh()->update(['status' => 'pending_verification']);
        $service->requestRevision($booking, $this->admin, 'round two', ['pax']);

        $this->assertSame(1, $booking->versions()->count());
    }

    public function test_the_snapshot_stores_display_labels_beside_every_foreign_key(): void
    {
        $booking = $this->booking('pending_verification');
        app(BookingService::class)->requestRevision($booking, $this->admin, 'x', ['pax']);

        $payload = $booking->versions()->first()->payload;

        // The diff renderer must work from JSON alone — no lookups, no N+1.
        $this->assertSame('Umrah 12D', data_get($payload, 'booking.package_title'));
        $this->assertSame('Quad', data_get($payload, 'rooms.0.room_name'));
        $this->assertSame(1, data_get($payload, 'v'));
        // Money as strings, or json_encode turns 17000.00 into 17000.
        $this->assertIsString(data_get($payload, 'money.total_amount'));
    }

    public function test_pax_are_keyed_by_id_so_a_deleted_row_does_not_shift_the_others(): void
    {
        $booking = $this->booking('pending_verification');
        app(BookingService::class)->requestRevision($booking, $this->admin, 'x', ['pax']);

        $pax = data_get($booking->versions()->first()->payload, 'pax');
        $this->assertSame((string) $booking->pax[0]->id, $pax[0]['key']);
        $this->assertSame((string) $booking->pax[1]->id, $pax[1]['key']);
    }
}
