<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentUpcomingTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Langkawi Cruise 3D2N', 'slug' => 'langkawi',
            'category' => 'cruise', 'status' => 'active', 'duration_days' => 3, 'duration_nights' => 2,
        ]);
    }

    private function booking(array $attrs = [], ?string $depart = null, ?string $return = null): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create([
            'name' => $attrs['customer'] ?? "Customer {$n}",
            'phone' => $attrs['phone'] ?? '01' . str_pad((string) $n, 8, '0'),
            'agent_id' => $this->agent->id,
        ]);

        $date = $depart ? PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => $depart,
            'return_date' => $return, 'seats_total' => 40, 'status' => 'open',
        ]) : null;

        return Booking::create([
            'booking_no'      => "BK-UP-{$n}",
            'package_id'      => $this->package->id,
            'package_date_id' => $date?->id,
            'customer_id'     => $customer->id,
            'agent_id'        => $this->agent->id,
            'status'          => $attrs['status'] ?? 'confirmed',
            'travel_date'     => $attrs['travel_date'] ?? $depart,
            'adults'          => $attrs['adults'] ?? 2,
            'children'        => $attrs['children'] ?? 0,
            'seniors'         => $attrs['seniors'] ?? 0,
            'total_pax'       => 2,
        ]);
    }

    public function test_it_lists_only_future_trips(): void
    {
        $this->booking([], now()->addMonth()->format('Y-m-d'), now()->addMonth()->addDays(2)->format('Y-m-d'));
        $this->booking([], now()->subMonth()->format('Y-m-d'), now()->subMonth()->addDays(2)->format('Y-m-d'));

        $response = $this->actingAs($this->agent)->get(route('agent.upcoming'))->assertOk();

        $this->assertCount(1, $response->viewData('bookings'), 'A past departure is not upcoming.');
    }

    public function test_cancelled_and_completed_trips_are_excluded(): void
    {
        $future = now()->addMonth()->format('Y-m-d');
        $this->booking(['status' => 'confirmed'], $future);
        foreach (['cancelled', 'rejected', 'refunded', 'completed'] as $status) {
            $this->booking(['status' => $status], $future);
        }

        $bookings = $this->actingAs($this->agent)->get(route('agent.upcoming'))->viewData('bookings');

        $this->assertCount(1, $bookings);
        $this->assertSame('confirmed', $bookings->first()->status);
    }

    /** An open-dated booking has no departure row — it must still show. */
    public function test_an_open_dated_booking_without_a_departure_still_appears(): void
    {
        $this->booking(['travel_date' => now()->addMonth()->format('Y-m-d')]);

        $bookings = $this->actingAs($this->agent)->get(route('agent.upcoming'))->viewData('bookings');

        $this->assertCount(1, $bookings);
        $this->assertNull($bookings->first()->package_date_id);
    }

    public function test_arrival_order_sorts_by_the_trip_date_not_the_booking_date(): void
    {
        // Created first but departing later, so arrival order must put it second.
        $later = $this->booking([], now()->addMonths(3)->format('Y-m-d'));
        $sooner = $this->booking([], now()->addMonth()->format('Y-m-d'));

        $bookings = $this->actingAs($this->agent)
            ->get(route('agent.upcoming', ['by' => 'arrival']))->viewData('bookings');

        $this->assertSame([$sooner->id, $later->id], $bookings->pluck('id')->all());
    }

    public function test_reservation_order_sorts_by_when_it_was_booked_newest_first(): void
    {
        $older = $this->booking([], now()->addMonth()->format('Y-m-d'));
        $older->update(['created_at' => now()->subWeek()]);
        $newer = $this->booking([], now()->addMonths(3)->format('Y-m-d'));

        $bookings = $this->actingAs($this->agent)
            ->get(route('agent.upcoming', ['by' => 'reservation']))->viewData('bookings');

        $this->assertSame([$newer->id, $older->id], $bookings->pluck('id')->all());
    }

    public function test_the_card_shows_name_dates_nights_pax_and_package(): void
    {
        $this->booking(
            ['customer' => 'Pietro Enrico Sergio', 'adults' => 3, 'children' => 2],
            '2026-08-14',
            '2026-08-19'
        );

        $this->actingAs($this->agent)->get(route('agent.upcoming'))
            ->assertOk()
            ->assertSee('Pietro Enrico Sergio')
            ->assertSee('Aug 14')
            ->assertSee('Aug 19, 2026')
            ->assertSee('5 nights')
            ->assertSee('3 adults, 2 children')
            ->assertSee('Langkawi Cruise 3D2N');
    }

    /** No departure row means no return date — nights fall back to the package. */
    public function test_nights_fall_back_to_the_package_duration(): void
    {
        $booking = $this->booking(['travel_date' => now()->addMonth()->format('Y-m-d')]);

        $this->assertSame(2, $booking->nights());
        $this->assertSame('2 adults', $booking->paxSummary());
    }

    public function test_a_single_passenger_is_not_pluralised(): void
    {
        $booking = $this->booking(['adults' => 1, 'children' => 1], now()->addMonth()->format('Y-m-d'));

        $this->assertSame('1 adult, 1 child', $booking->paxSummary());
    }

    public function test_it_can_be_searched_by_customer_and_package(): void
    {
        $future = now()->addMonth()->format('Y-m-d');
        $this->booking(['customer' => 'Nur Aina'], $future);
        $this->booking(['customer' => 'Someone Else'], $future);

        $byName = $this->actingAs($this->agent)->get(route('agent.upcoming', ['q' => 'Nur Aina']));
        $this->assertCount(1, $byName->viewData('bookings'));

        $byPackage = $this->actingAs($this->agent)->get(route('agent.upcoming', ['q' => 'Langkawi']));
        $this->assertCount(2, $byPackage->viewData('bookings'));
    }

    public function test_it_never_shows_another_agents_trips(): void
    {
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $mine = $this->booking([], now()->addMonth()->format('Y-m-d'));
        $theirs = $this->booking([], now()->addMonth()->format('Y-m-d'));
        $theirs->update(['agent_id' => $other->id]);

        $bookings = $this->actingAs($this->agent)->get(route('agent.upcoming'))->viewData('bookings');

        $this->assertCount(1, $bookings);
        $this->assertSame($mine->id, $bookings->first()->id);
    }
}
