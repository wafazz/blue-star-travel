<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function package(int $nights = 0): Package
    {
        static $n = 0;
        $n++;
        $provider = Provider::create(['name' => "Prov D{$n}", 'type' => 'local_operator', 'status' => 'active']);

        return Package::create([
            'code' => "PKG-D{$n}", 'title' => "Pkg D{$n}", 'slug' => "pkg-d-{$n}", 'category' => 'domestic',
            'status' => 'active', 'provider_id' => $provider->id, 'duration_nights' => $nights,
        ]);
    }

    private function booking(string $no, array $extra = []): Booking
    {
        $customer = Customer::create(['name' => "Cust {$no}", 'agent_id' => $this->agent->id]);

        return Booking::create([
            'booking_no'  => $no,
            'package_id'  => $this->package(4)->id,
            'customer_id' => $customer->id,
            'agent_id'    => $this->agent->id,
            'status'      => 'pending_verification',
        ] + $extra);
    }

    private function list(array $query = []): string
    {
        return $this->actingAs($this->admin)->get(route('manage.bookings.index', $query))
            ->assertOk()->getContent();
    }

    public function test_checkin_range_uses_the_typed_travel_date(): void
    {
        $in = $this->booking('BK-IN-1', ['travel_date' => '2026-12-20']);
        $out = $this->booking('BK-IN-2', ['travel_date' => '2027-01-10']);

        $html = $this->list(['checkin_from' => '2026-12-15', 'checkin_to' => '2026-12-31']);
        $this->assertStringContainsString($in->booking_no, $html);
        $this->assertStringNotContainsString($out->booking_no, $html);
    }

    public function test_checkin_uses_the_chosen_departure_before_the_typed_date(): void
    {
        $package = $this->package(3);
        $departure = PackageDate::create([
            'package_id' => $package->id, 'depart_date' => '2026-11-28', 'return_date' => '2026-12-01',
        ]);
        // Typed date says Dec, but the chosen departure is Nov 28.
        $booking = $this->booking('BK-DEP-1', [
            'package_id' => $package->id, 'package_date_id' => $departure->id, 'travel_date' => '2026-12-20',
        ]);

        $this->assertStringContainsString($booking->booking_no, $this->list(['checkin_from' => '2026-11-25', 'checkin_to' => '2026-11-30']));
        $this->assertStringNotContainsString($booking->booking_no, $this->list(['checkin_from' => '2026-12-15', 'checkin_to' => '2026-12-31']));
    }

    public function test_checkout_uses_the_typed_return_date(): void
    {
        $booking = $this->booking('BK-OUT-1', ['travel_date' => '2026-12-20', 'return_date' => '2026-12-27']);

        $this->assertStringContainsString($booking->booking_no, $this->list(['checkout_from' => '2026-12-26', 'checkout_to' => '2026-12-28']));
        $this->assertStringNotContainsString($booking->booking_no, $this->list(['checkout_from' => '2026-12-20', 'checkout_to' => '2026-12-24']));
    }

    public function test_checkout_is_worked_out_from_check_in_plus_package_nights_when_not_stored(): void
    {
        // 4-night package, no stored return date: 20 Dec + 4 nights = 24 Dec.
        $booking = $this->booking('BK-DRV-1', ['travel_date' => '2026-12-20']);

        $this->assertStringContainsString($booking->booking_no, $this->list(['checkout_from' => '2026-12-24', 'checkout_to' => '2026-12-24']));
        $this->assertStringNotContainsString($booking->booking_no, $this->list(['checkout_from' => '2026-12-25', 'checkout_to' => '2026-12-31']));
    }

    public function test_checkin_and_checkout_filters_combine(): void
    {
        $match = $this->booking('BK-COMBO-1', ['travel_date' => '2026-12-20']);       // out 24 Dec
        $wrongOut = $this->booking('BK-COMBO-2', ['travel_date' => '2026-12-20', 'return_date' => '2027-01-05']);

        $html = $this->list(['checkin_from' => '2026-12-01', 'checkin_to' => '2026-12-31', 'checkout_from' => '2026-12-22', 'checkout_to' => '2026-12-31']);
        $this->assertStringContainsString($match->booking_no, $html);
        $this->assertStringNotContainsString($wrongOut->booking_no, $html);
    }

    public function test_open_ended_range_works_with_one_side(): void
    {
        $early = $this->booking('BK-OPEN-1', ['travel_date' => '2026-11-01']);
        $late = $this->booking('BK-OPEN-2', ['travel_date' => '2027-02-01']);

        $html = $this->list(['checkin_from' => '2027-01-01']);
        $this->assertStringContainsString($late->booking_no, $html);
        $this->assertStringNotContainsString($early->booking_no, $html);
    }

    public function test_no_dates_means_no_filter(): void
    {
        $a = $this->booking('BK-ALL-1', ['travel_date' => '2026-11-01']);
        $b = $this->booking('BK-ALL-2', ['travel_date' => '2027-02-01']);

        $html = $this->list();
        $this->assertStringContainsString($a->booking_no, $html);
        $this->assertStringContainsString($b->booking_no, $html);
    }

    public function test_an_invalid_date_is_refused(): void
    {
        $this->actingAs($this->admin)->get(route('manage.bookings.index', ['checkin_from' => 'not-a-date']))
            ->assertSessionHasErrors('checkin_from');
    }
}
