<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPostponement;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\PackagePricing;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PartialPostponementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PartialPostponementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;
    private PackageDate $main;
    private PackageDate $own;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-P', 'title' => 'Group Tour', 'slug' => 'group-tour',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'fixed',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 1000, 'child_price' => 800, 'senior_price' => 1000, 'infant_price' => 0, 'is_default' => true,
        ]);
        $this->main = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-12-01',
            'seats_total' => 20, 'seats_booked' => 0, 'status' => 'open',
        ]);
        $this->own = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2027-01-15',
            'seats_total' => 10, 'seats_booked' => 0, 'status' => 'open',
        ]);
    }

    /** 4 adults on the main departure, confirmed, fully paid. The departure holds their 4 seats. */
    private function booking(string $status = 'confirmed'): Booking
    {
        static $n = 0;
        $n++;

        $this->main->increment('seats_booked', 4);
        $customer = Customer::create(['name' => 'Group Lead', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);
        $booking = Booking::create([
            'booking_no' => "BK-PP-{$n}", 'package_id' => $this->package->id, 'package_date_id' => $this->main->id,
            'customer_id' => $customer->id, 'agent_id' => $this->agent->id, 'status' => $status,
            'travel_date' => '2026-12-01', 'adults' => 4, 'total_pax' => 4, 'subtotal' => 4000,
            'total_amount' => 4000, 'paid_amount' => 4000,
        ]);
        $booking->rooms()->create([
            'package_pricing_id' => $this->quad->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 4, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 1000, 'child_price' => 800, 'senior_price' => 1000, 'infant_price' => 0,
            'subtotal' => 4000,
        ]);

        return $booking;
    }

    private function postpone(Booking $booking, array $counts): BookingPostponement
    {
        return app(PartialPostponementService::class)->postpone($booking, $counts, 'Family emergency', $this->staff);
    }

    public function test_postponing_one_passenger_releases_their_seat_and_keeps_the_fare(): void
    {
        $booking = $this->booking();

        $group = $this->postpone($booking, ['adults' => 1]);

        $this->assertSame(BookingPostponement::PENDING, $group->status);
        $this->assertSame(3, $booking->refresh()->mainPax());
        $this->assertSame('confirmed', $booking->status, 'The rest of the group still travels.');
        $this->assertSame(4000.0, (float) $booking->total_amount, 'No refund and no re-price.');
        $this->assertSame(4000.0, (float) $booking->paid_amount);
        $this->assertSame(3, $this->main->refresh()->seats_booked, 'One seat released on the main departure.');
    }

    public function test_redating_gives_the_passenger_their_own_departure_and_seat(): void
    {
        $booking = $this->booking();
        $group = $this->postpone($booking, ['adults' => 1]);

        app(PartialPostponementService::class)->redate($group, $this->own->id, $this->staff);

        $group->refresh();
        $this->assertSame(BookingPostponement::REDATED, $group->status);
        $this->assertSame($this->own->id, $group->package_date_id);
        $this->assertSame(1, $this->own->refresh()->seats_booked);
        $this->assertSame(3, $this->main->refresh()->seats_booked, 'The main departure is untouched by the redate.');
        $this->assertSame($booking->id, $group->booking_id, 'Still recorded under the original booking.');
    }

    public function test_cannot_postpone_everyone_still_travelling(): void
    {
        $booking = $this->booking();

        $this->expectException(ValidationException::class);
        $this->postpone($booking, ['adults' => 4]);
    }

    public function test_cannot_postpone_more_of_a_type_than_the_booking_has(): void
    {
        $booking = $this->booking();
        $this->postpone($booking, ['adults' => 2]);

        try {
            $this->postpone($booking, ['adults' => 3]);
            $this->fail('Expected the second postponement to be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('adults', $e->errors());
        }
        $this->assertSame(1, $booking->postponements()->count());
    }

    public function test_a_redate_needs_seats_on_the_chosen_departure(): void
    {
        $booking = $this->booking();
        $group = $this->postpone($booking, ['adults' => 2]);
        $this->own->update(['seats_total' => 10, 'seats_booked' => 9]);

        $this->expectException(ValidationException::class);
        app(PartialPostponementService::class)->redate($group, $this->own->id, $this->staff);
    }

    public function test_a_passenger_cannot_be_redated_twice(): void
    {
        $booking = $this->booking();
        $group = $this->postpone($booking, ['adults' => 1]);
        app(PartialPostponementService::class)->redate($group, $this->own->id, $this->staff);

        $this->expectException(ValidationException::class);
        app(PartialPostponementService::class)->redate($group->refresh(), $this->own->id, $this->staff);
    }

    public function test_only_a_confirmed_departure_booking_can_postpone_passengers(): void
    {
        $booking = $this->booking('pending_verification');

        $this->expectException(ValidationException::class);
        $this->postpone($booking, ['adults' => 1]);
    }

    public function test_cancelling_releases_the_seats_both_the_main_and_the_redated_departure_hold(): void
    {
        $booking = $this->booking();
        $group = $this->postpone($booking, ['adults' => 1]);
        app(PartialPostponementService::class)->redate($group, $this->own->id, $this->staff);

        app(BookingService::class)->cancel($booking->refresh(), $this->staff, 'Trip cancelled');

        $this->assertSame(0, $this->main->refresh()->seats_booked);
        $this->assertSame(0, $this->own->refresh()->seats_booked);
    }

    public function test_staff_can_postpone_and_redate_through_the_routes(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->staff)
            ->post(route('manage.bookings.postpone-pax', $booking), ['adults' => 1, 'reason' => 'Visa delay'])
            ->assertRedirect();
        $group = $booking->postponements()->first();
        $this->assertNotNull($group);

        $this->actingAs($this->staff)
            ->post(route('manage.bookings.postponements.redate', [$booking, $group]), ['package_date_id' => $this->own->id])
            ->assertRedirect();

        $this->assertSame(BookingPostponement::REDATED, $group->refresh()->status);
    }

    public function test_a_redate_for_another_bookings_group_is_not_reachable(): void
    {
        $mine = $this->booking();
        $other = $this->booking();
        $group = $this->postpone($other, ['adults' => 1]);

        $this->actingAs($this->staff)
            ->post(route('manage.bookings.postponements.redate', [$mine, $group]), ['package_date_id' => $this->own->id])
            ->assertNotFound();
    }
}
