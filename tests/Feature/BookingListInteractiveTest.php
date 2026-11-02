<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingListInteractiveTest extends TestCase
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

    private function booking(string $no, array $extra = []): Booking
    {
        static $n = 0;
        $n++;
        $provider = Provider::create(['name' => "Prov I{$n}", 'type' => 'local_operator', 'status' => 'active']);
        $package = Package::create([
            'code' => "PKG-I{$n}", 'title' => "Pkg I{$n}", 'slug' => "pkg-i-{$n}", 'category' => 'domestic',
            'status' => 'active', 'provider_id' => $provider->id, 'duration_nights' => 0,
        ]);
        $customer = Customer::create(['name' => "Cust I{$n}", 'agent_id' => $this->agent->id]);

        return Booking::create([
            'booking_no' => $no, 'package_id' => $package->id, 'customer_id' => $customer->id,
            'agent_id' => $this->agent->id, 'provider_id' => $provider->id, 'status' => 'pending_verification',
        ] + $extra);
    }

    /** Order of booking numbers as they appear in the list HTML. */
    private function order(array $query): array
    {
        $html = $this->actingAs($this->admin)->get(route('manage.bookings.index', $query))->assertOk()->getContent();
        preg_match_all('/BK-SRT-\d/', $html, $m);

        return array_values(array_unique($m[0]));
    }

    public function test_sorting_by_checkin_both_directions(): void
    {
        $this->booking('BK-SRT-1', ['travel_date' => '2026-12-20']);
        $this->booking('BK-SRT-2', ['travel_date' => '2026-11-05']);
        $this->booking('BK-SRT-3', ['travel_date' => '2027-01-15']);

        $this->assertSame(['BK-SRT-2', 'BK-SRT-1', 'BK-SRT-3'], $this->order(['sort' => 'checkin', 'dir' => 'asc']));
        $this->assertSame(['BK-SRT-3', 'BK-SRT-1', 'BK-SRT-2'], $this->order(['sort' => 'checkin', 'dir' => 'desc']));
    }

    public function test_sorting_by_checkout_uses_the_same_rule_as_the_filter(): void
    {
        // 2 nights, no stored return: checkout = check-in + 2 days.
        $this->booking('BK-SRT-1', ['travel_date' => '2026-12-20', 'return_date' => '2026-12-30']);
        $this->booking('BK-SRT-2', ['travel_date' => '2026-12-01']);

        $this->assertSame(['BK-SRT-2', 'BK-SRT-1'], $this->order(['sort' => 'checkout', 'dir' => 'asc']));
    }

    public function test_sorting_by_total(): void
    {
        $this->booking('BK-SRT-1', ['total_amount' => 900]);
        $this->booking('BK-SRT-2', ['total_amount' => 100]);
        $this->booking('BK-SRT-3', ['total_amount' => 500]);

        $this->assertSame(['BK-SRT-2', 'BK-SRT-3', 'BK-SRT-1'], $this->order(['sort' => 'total', 'dir' => 'asc']));
    }

    public function test_an_unknown_sort_key_is_ignored_safely(): void
    {
        $this->booking('BK-SRT-1', ['created_at' => now()->subHour()]);
        $this->booking('BK-SRT-2', ['created_at' => now()]);

        $this->actingAs($this->admin)->get(route('manage.bookings.index', ['sort' => 'drop table users', 'dir' => 'sideways']))
            ->assertOk();
        $this->assertSame(['BK-SRT-2', 'BK-SRT-1'], $this->order(['sort' => 'nope']));
    }

    public function test_sort_header_links_flip_direction_and_keep_filters(): void
    {
        $this->booking('BK-SRT-1');

        $html = $this->actingAs($this->admin)
            ->get(route('manage.bookings.index', ['q' => 'BK', 'sort' => 'checkin', 'dir' => 'asc']))
            ->assertOk()->getContent();

        // The active column's link flips to desc and carries the search term along.
        $this->assertStringContainsString('q=BK', $html);
        $this->assertStringContainsString('dir=desc', $html);
    }

    public function test_active_filters_show_as_removable_chips(): void
    {
        $this->booking('BK-SRT-1');

        $this->actingAs($this->admin)
            ->get(route('manage.bookings.index', ['q' => 'Cust I1', 'checkin_from' => '2026-12-01']))
            ->assertOk()
            ->assertSee('Search: Cust I1')
            ->assertSee('Check-in from: 2026-12-01')
            ->assertSee('Clear all');
    }

    public function test_rows_link_to_the_booking_and_carry_data_labels_for_phones(): void
    {
        $booking = $this->booking('BK-SRT-1');

        $this->actingAs($this->admin)->get(route('manage.bookings.index'))
            ->assertOk()
            ->assertSee('data-href="' . route('manage.bookings.show', $booking) . '"', false)
            ->assertSee('data-label="Check-in / Check-out"', false)
            ->assertSee('data-label="Remark"', false);
    }

    public function test_remark_saves_over_json_for_the_inline_editor(): void
    {
        $booking = $this->booking('BK-SRT-1');

        $this->actingAs($this->admin)
            ->postJson(route('manage.bookings.remark', $booking), ['internal_notes' => 'Call back Monday'])
            ->assertOk()
            ->assertJson(['saved' => true, 'remark' => 'Call back Monday']);

        $this->assertSame('Call back Monday', $booking->refresh()->internal_notes);
    }

    public function test_an_invalid_remark_returns_a_json_error_the_editor_can_show(): void
    {
        $booking = $this->booking('BK-SRT-1');

        $this->actingAs($this->admin)
            ->postJson(route('manage.bookings.remark', $booking), ['internal_notes' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('internal_notes');
    }
}
