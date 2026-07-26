<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\PackagePricing;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;
    private PackageDate $oldDate;
    private PackageDate $newDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'fixed',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0, 'is_default' => true,
        ]);
        $this->oldDate = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-11-03',
            'seats_total' => 40, 'seats_booked' => 2, 'status' => 'open',
        ]);
        $this->newDate = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-12-08',
            'seats_total' => 40, 'seats_booked' => 0, 'status' => 'open',
        ]);
    }

    private function booking(string $status = 'confirmed'): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Nur Aina', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);
        $booking = Booking::create([
            'booking_no' => "BK-AMD-{$n}", 'package_id' => $this->package->id,
            'package_date_id' => $this->oldDate->id, 'customer_id' => $customer->id,
            'agent_id' => $this->agent->id, 'status' => $status, 'travel_date' => '2026-11-03',
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

    private function request(Booking $booking, array $data = [])
    {
        return $this->actingAs($this->agent)->post(route('agent.bookings.amendment', $booking), $data + [
            'type'                      => 'travel_date',
            'reason'                    => 'Customer requested a new travel date.',
            'requested_package_date_id' => $this->newDate->id,
            'requested_date'            => '2026-12-08',
        ]);
    }

    public function test_an_agent_requests_a_date_change_on_a_confirmed_booking(): void
    {
        $booking = $this->booking();

        $this->request($booking)->assertRedirect();

        $amendment = $booking->amendments()->first();
        $this->assertSame('pending', $amendment->status);
        $this->assertSame('travel_date', $amendment->type);
        $this->assertSame($this->agent->id, $amendment->requested_by);
        $this->assertSame('03 Nov 2026', $amendment->current_value, 'The old value is frozen at request time.');
        $this->assertSame('confirmed', $booking->refresh()->status, 'Requesting must not move the booking.');
    }

    public function test_staff_are_notified_and_the_timeline_records_the_request(): void
    {
        $booking = $this->booking();
        $this->request($booking);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title'   => "Amendment requested on {$booking->booking_no}",
        ]);
        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Amendment requested — Change Date',
        ]);
    }

    public function test_approving_moves_the_seats_between_departures_in_one_transaction(): void
    {
        $booking = $this->booking();
        $this->request($booking);

        $this->actingAs($this->admin)->post(
            route('manage.bookings.amendments.approve', [$booking, $booking->amendments()->first()]),
            ['admin_note' => 'Approved by ops.']
        )->assertRedirect();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status, 'The booking never leaves confirmed.');
        $this->assertSame($this->newDate->id, $booking->package_date_id);
        $this->assertSame('2026-12-08', $booking->travel_date->format('Y-m-d'));

        // 2 seats released on the old departure, 2 taken on the new one.
        $this->assertSame(0, $this->oldDate->refresh()->seats_booked);
        $this->assertSame(2, $this->newDate->refresh()->seats_booked);
    }

    /** Regression: a booking with no departure must still take seats when it gains one. */
    public function test_moving_from_no_departure_onto_one_still_reserves_the_seats(): void
    {
        $booking = $this->booking();
        $booking->update(['package_date_id' => null]);
        $this->request($booking);

        app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);

        $this->assertSame($this->newDate->id, $booking->refresh()->package_date_id);
        $this->assertSame(2, $this->newDate->refresh()->seats_booked, 'The new departure must hold this booking\'s pax.');
    }

    public function test_approving_writes_an_amendment_version_and_reissues_documents(): void
    {
        $booking = $this->booking();
        $this->request($booking);
        app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);

        $version = $booking->refresh()->versions()->first();
        $this->assertSame('amendment', $version->reason);
        $this->assertSame('Approved amendment', $version->reasonLabel());

        $changed = collect($version->changes)->pluck('key');
        $this->assertTrue($changed->contains('booking.travel_date'));

        $this->assertNotNull($booking->document('invoice'));
        $this->assertNotNull($booking->document('voucher'));
    }

    public function test_a_pickup_amendment_applies_the_new_details(): void
    {
        $booking = $this->booking();

        $this->request($booking, [
            'type'                      => 'pickup',
            'requested_pickup_location' => 'KLIA2 Gate C, Level 2',
            'requested_arrival_time'    => '06:30',
            'requested_package_date_id' => null,
            'requested_date'            => null,
        ])->assertRedirect();

        app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);

        $booking->refresh();
        $this->assertSame('KLIA2 Gate C, Level 2', $booking->pickup_location);
        // MySQL returns 06:30:00, SQLite echoes 06:30 — views normalise with substr().
        $this->assertSame('06:30', substr($booking->arrival_time, 0, 5));
        // The departure must not move on a pickup-only amendment.
        $this->assertSame($this->oldDate->id, $booking->package_date_id);
    }

    public function test_an_other_amendment_is_recorded_but_applies_nothing(): void
    {
        $booking = $this->booking();

        $this->request($booking, [
            'type' => 'other', 'reason' => 'Customer wants a different hotel.',
            'requested_package_date_id' => null, 'requested_date' => null,
        ]);
        app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);

        $booking->refresh();
        $this->assertSame('approved', $booking->amendments()->first()->status);
        $this->assertSame($this->oldDate->id, $booking->package_date_id, 'An `other` amendment is staff-actioned by hand.');
        $this->assertSame('2026-11-03', $booking->travel_date->format('Y-m-d'));
    }

    public function test_a_departure_without_room_is_refused_and_nothing_moves(): void
    {
        $full = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2027-01-05',
            'seats_total' => 2, 'seats_booked' => 2, 'status' => 'open',
        ]);
        $booking = $this->booking();
        $this->request($booking, ['requested_package_date_id' => $full->id, 'requested_date' => '2027-01-05']);

        try {
            app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);
            $this->fail('A full departure must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requested_package_date_id', $e->errors());
        }

        $this->assertSame($this->oldDate->id, $booking->refresh()->package_date_id);
        $this->assertSame(2, $this->oldDate->refresh()->seats_booked, 'Seats must not move when the guard trips.');
        $this->assertSame('pending', $booking->amendments()->first()->status);
    }

    public function test_rejecting_leaves_the_booking_untouched(): void
    {
        $booking = $this->booking();
        $this->request($booking);

        $this->actingAs($this->admin)->post(
            route('manage.bookings.amendments.reject', [$booking, $booking->amendments()->first()]),
            ['admin_note' => 'Departure is full.']
        )->assertRedirect();

        $booking->refresh();
        $this->assertSame('rejected', $booking->amendments()->first()->status);
        $this->assertSame($this->oldDate->id, $booking->package_date_id);
        $this->assertSame(2, $this->oldDate->refresh()->seats_booked);
        $this->assertSame(0, $booking->versions()->count());
    }

    public function test_only_confirmed_bookings_can_be_amended(): void
    {
        foreach (['pending_verification', 'needs_revision', 'cancelled', 'completed'] as $status) {
            $booking = $this->booking($status);

            $this->expectException(ValidationException::class);
            app(BookingService::class)->requestAmendment($booking, $this->agent, [
                'type' => 'travel_date', 'reason' => 'x', 'requested_package_date_id' => $this->newDate->id,
            ]);
        }
    }

    public function test_only_one_amendment_may_be_open_at_a_time(): void
    {
        $booking = $this->booking();
        $this->request($booking);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->requestAmendment($booking, $this->agent, [
            'type' => 'travel_date', 'reason' => 'again', 'requested_package_date_id' => $this->newDate->id,
        ]);
    }

    public function test_another_agent_cannot_request_an_amendment(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($other)->post(route('agent.bookings.amendment', $booking), [
            'type' => 'travel_date', 'reason' => 'x', 'requested_package_date_id' => $this->newDate->id,
        ])->assertForbidden();
    }

    public function test_an_agent_cannot_approve_their_own_amendment(): void
    {
        $booking = $this->booking();
        $this->request($booking);

        $this->actingAs($this->agent)->post(
            route('manage.bookings.amendments.approve', [$booking, $booking->amendments()->first()])
        )->assertForbidden();
    }

    public function test_an_amendment_from_another_booking_is_not_reachable(): void
    {
        $mine = $this->booking();
        $theirs = $this->booking();
        $this->request($theirs);

        $this->actingAs($this->admin)->post(
            route('manage.bookings.amendments.approve', [$mine, $theirs->amendments()->first()])
        )->assertNotFound();
    }

    public function test_a_reason_is_required(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->post(route('agent.bookings.amendment', $booking), [
            'type' => 'travel_date', 'reason' => '', 'requested_package_date_id' => $this->newDate->id,
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, $booking->amendments()->count());
    }

    public function test_an_amendment_cannot_be_approved_twice(): void
    {
        $booking = $this->booking();
        $this->request($booking);
        $amendment = $booking->amendments()->first();
        app(BookingService::class)->approveAmendment($amendment, $this->admin);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->approveAmendment($amendment->refresh(), $this->admin);
    }

    public function test_commission_is_left_alone_when_the_total_does_not_move(): void
    {
        $booking = $this->booking();
        Commission::create([
            'booking_id' => $booking->id, 'earner_id' => $this->agent->id, 'level' => 1,
            'period' => now()->format('Y-m'), 'amount' => 1360, 'status' => 'approved',
        ]);
        $this->request($booking);

        // A date change does not move the price, so the ledger must be untouched.
        app(BookingService::class)->approveAmendment($booking->amendments()->first(), $this->admin);

        $this->assertSame('approved', Commission::where('booking_id', $booking->id)->first()->status);
    }
}
