<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\PackagePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Two client rules, one flow:
 *  - a date change is not reviewable without supporting evidence;
 *  - postponing is NOT the same as going open-dated.
 */
class DateChangeEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;
    private PackageDate $departure;
    private PackageDate $laterDeparture;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-PP', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d-pp',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'fixed', 'duration_nights' => 12,
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0, 'is_default' => true,
        ]);
        $this->departure = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-11-03',
            'seats_total' => 40, 'seats_booked' => 2, 'status' => 'open',
        ]);
        $this->laterDeparture = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-12-08',
            'seats_total' => 40, 'seats_booked' => 0, 'status' => 'open',
        ]);
    }

    private function booking(): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Nur Aina', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);
        $booking = Booking::create([
            'booking_no' => "BK-PP-{$n}", 'package_id' => $this->package->id,
            'package_date_id' => $this->departure->id, 'customer_id' => $customer->id,
            'agent_id' => $this->agent->id, 'status' => 'confirmed', 'travel_date' => '2026-11-03',
            'return_date' => '2026-11-15',
            'adults' => 2, 'total_pax' => 2, 'subtotal' => 17000, 'total_amount' => 17000, 'paid_amount' => 17000,
        ]);
        $booking->rooms()->create([
            'package_pricing_id' => $this->quad->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0,
            'subtotal' => 17000,
        ]);
        $booking->pax()->create(['name' => 'Nur Aina', 'type' => 'adult', 'is_lead' => true]);

        return $booking;
    }

    private function evidence(): UploadedFile
    {
        return UploadedFile::fake()->create('whatsapp-request.pdf', 20, 'application/pdf');
    }

    private function request(Booking $booking, array $data)
    {
        return $this->actingAs($this->agent)->post(route('agent.bookings.amendment', $booking), $data + [
            'type'   => 'travel_date',
            'reason' => 'Customer asked to move the trip.',
        ]);
    }

    private function approve(Booking $booking)
    {
        $amendment = $booking->amendments()->latest('id')->first();

        return $this->actingAs($this->admin)
            ->post(route('manage.bookings.amendments.approve', [$booking, $amendment]));
    }

    // ---- Rule 1: evidence is mandatory ------------------------------------

    public function test_a_date_change_without_a_document_is_refused(): void
    {
        $booking = $this->booking();

        $this->request($booking, ['requested_date' => '2026-12-08'])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, $booking->amendments()->count());
    }

    public function test_a_postponement_without_a_document_is_refused_too(): void
    {
        $booking = $this->booking();

        $this->request($booking, ['is_postponement' => 1])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, $booking->amendments()->count());
    }

    public function test_a_pickup_change_still_needs_no_document(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->post(route('agent.bookings.amendment', $booking), [
            'type'                      => 'pickup',
            'reason'                    => 'Different terminal.',
            'requested_pickup_location' => 'KLIA2 Gate C',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $booking->amendments()->count());
    }

    public function test_the_document_is_stored_privately_and_reachable_by_staff_and_the_owning_agent(): void
    {
        $booking = $this->booking();
        $this->request($booking, ['requested_date' => '2026-12-08', 'attachment' => $this->evidence()])
            ->assertSessionHasNoErrors();

        $amendment = $booking->amendments()->first();
        $this->assertNotNull($amendment->attachment_path);
        Storage::disk('local')->assertExists($amendment->attachment_path);

        $this->actingAs($this->admin)->get(route('amendments.attachment', $amendment))->assertOk();
        $this->actingAs($this->agent)->get(route('amendments.attachment', $amendment))->assertOk();

        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($other)->get(route('amendments.attachment', $amendment))->assertForbidden();
    }

    public function test_an_executable_is_not_accepted_as_evidence(): void
    {
        $booking = $this->booking();

        $this->request($booking, [
            'requested_date' => '2026-12-08',
            'attachment'     => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('attachment');
    }

    // ---- Rule 2: postponed is not open-dated ------------------------------

    public function test_approving_a_postponement_parks_the_booking_without_a_date(): void
    {
        $booking = $this->booking();
        $this->request($booking, ['is_postponement' => 1, 'attachment' => $this->evidence()])
            ->assertSessionHasNoErrors();

        $this->approve($booking)->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('postponed', $booking->status);
        $this->assertNull($booking->travel_date);
        $this->assertNull($booking->return_date);
        $this->assertNull($booking->package_date_id);
        $this->assertNull($booking->arrivalDate(), 'A postponed trip has no date to derive from.');

        // The package is what is open-dated or not. Parking one booking must not touch it.
        $this->assertSame('fixed', $this->package->fresh()->date_mode);
    }

    public function test_a_postponement_releases_the_seats(): void
    {
        $booking = $this->booking();
        $this->assertSame(2, $this->departure->fresh()->seats_booked);

        $this->request($booking, ['is_postponement' => 1, 'attachment' => $this->evidence()]);
        $this->approve($booking);

        $this->assertSame(0, $this->departure->fresh()->seats_booked);
    }

    public function test_the_money_and_the_agents_sales_credit_survive_a_postponement(): void
    {
        $booking = $this->booking();
        $this->request($booking, ['is_postponement' => 1, 'attachment' => $this->evidence()]);
        $this->approve($booking);

        $booking->refresh();
        $this->assertEquals(17000, $booking->paid_amount);
        $this->assertEquals(17000, $booking->total_amount);
        $this->assertFalse($booking->isDead());
        $this->assertTrue(
            Booking::where('agent_id', $this->agent->id)->whereIn('status', Booking::SOLD_STATUSES)->exists(),
            'A postponed booking is still a sale — it must keep counting toward the agent.'
        );
    }

    public function test_a_postponed_booking_returns_to_confirmed_when_a_new_date_arrives(): void
    {
        $booking = $this->booking();
        $this->request($booking, ['is_postponement' => 1, 'attachment' => $this->evidence()]);
        $this->approve($booking);
        $this->assertSame('postponed', $booking->fresh()->status);

        $this->request($booking, [
            'requested_package_date_id' => $this->laterDeparture->id,
            'attachment'                => $this->evidence(),
        ])->assertSessionHasNoErrors();
        $this->approve($booking)->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($this->laterDeparture->id, $booking->package_date_id);
        $this->assertSame('2026-12-08', $booking->travel_date->format('Y-m-d'));
        $this->assertSame(2, $this->laterDeparture->fresh()->seats_booked, 'The seats are taken again on the new departure.');
    }

    public function test_the_agent_screen_shows_postponed_and_not_confirmed(): void
    {
        $booking = $this->booking();
        $this->request($booking, ['is_postponement' => 1, 'attachment' => $this->evidence()]);
        $this->approve($booking);

        $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Postponed');

        // The staff screen renders a booking with no dates at all — it must not blow up.
        $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Postponed');
    }

    public function test_the_amendment_form_offers_the_upload_and_the_postpone_option(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('name="attachment"', false)
            ->assertSee('name="is_postponement"', false)
            ->assertSee('Supporting document');
    }

    public function test_a_postponement_carries_no_date_even_if_the_form_posts_one(): void
    {
        $booking = $this->booking();

        $this->request($booking, [
            'is_postponement'           => 1,
            'requested_date'            => '2026-12-08',
            'requested_package_date_id' => $this->laterDeparture->id,
            'attachment'                => $this->evidence(),
        ])->assertSessionHasNoErrors();

        $amendment = $booking->amendments()->first();
        $this->assertTrue($amendment->isPostponement());
        $this->assertNull($amendment->requested_date);
        $this->assertNull($amendment->requested_package_date_id);
    }
}
