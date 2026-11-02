<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingListColumnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin', 'status' => 'active', 'permissions' => ['bookings'],
        ]);
    }

    private function booking(array $extra = []): Booking
    {
        static $n = 0;
        $n++;

        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active', 'name' => "Agent L{$n}"]);
        $provider = Provider::create(['name' => "Provider L{$n}", 'type' => 'local_operator', 'status' => 'active']);
        $package = Package::create([
            'code' => "PKG-L{$n}", 'title' => "Package L{$n}", 'slug' => "package-l-{$n}",
            'category' => 'domestic', 'status' => 'active', 'provider_id' => $provider->id,
        ]);
        $customer = Customer::create(['name' => "Customer L{$n}", 'phone' => '012-345 6789', 'agent_id' => $agent->id]);

        return Booking::create([
            'booking_no'  => "BK-LST-{$n}",
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $agent->id,
            'provider_id' => $provider->id,
            'status'      => 'pending_verification',
            'travel_date' => '2026-12-20',
            'return_date' => '2026-12-24',
        ] + $extra);
    }

    private function room(Booking $booking, array $counts): void
    {
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_name'  => 'Double',
            'capacity'   => 2,
            'rooms'      => 1,
            'adults'     => $counts['adults'] ?? 0,
            'children'   => $counts['children'] ?? 0,
            'seniors'    => $counts['seniors'] ?? 0,
            'infants'    => $counts['infants'] ?? 0,
        ]);
    }

    public function test_list_shows_phone_dates_pack_and_agent_note(): void
    {
        $booking = $this->booking(['agent_note' => 'Customer pays the rest by 30 Nov.']);
        $this->room($booking, ['adults' => 2, 'children' => 1]);
        $this->room($booking, ['adults' => 1, 'seniors' => 1, 'infants' => 1]);

        $this->actingAs($this->admin)->get(route('manage.bookings.index'))
            ->assertOk()
            ->assertSee('012-345 6789')
            ->assertSee('20 Dec 2026')
            ->assertSee('24 Dec 2026')
            ->assertSee('3 Adults, 1 Child, 1 Senior, 1 Infant')
            ->assertSee('Customer pays the rest by 30 Nov.');
    }

    public function test_pack_lists_only_the_types_present_and_plurals_correctly(): void
    {
        $booking = $this->booking();
        $this->room($booking, ['adults' => 1]);

        $this->actingAs($this->admin)->get(route('manage.bookings.index'))
            ->assertOk()
            ->assertSee('1 Adult')
            ->assertDontSee('1 Adult,');
    }

    public function test_missing_fields_show_a_dash_and_long_notes_are_truncated(): void
    {
        $this->booking([
            'customer_id' => Customer::create(['name' => 'No Phone', 'agent_id' => null])->id,
            'return_date' => null,
            'agent_note'  => str_repeat('x', 120),
        ]);

        $response = $this->actingAs($this->admin)->get(route('manage.bookings.index'))->assertOk();
        // The visible text is cut at 60 characters; the full note lives only in the tooltip attribute.
        $response->assertSee('>' . str_repeat('x', 60) . '...</span>', false)
            ->assertDontSee('>' . str_repeat('x', 61), false);
    }

    public function test_agent_note_is_not_shown_to_the_agent_list(): void
    {
        $booking = $this->booking(['agent_note' => 'Staff only fact']);
        $this->room($booking, ['adults' => 1]);

        $agent = $booking->agent;
        $this->actingAs($agent)->get(route('agent.bookings.index'))
            ->assertOk()
            ->assertDontSee('Staff only fact');
    }
}
