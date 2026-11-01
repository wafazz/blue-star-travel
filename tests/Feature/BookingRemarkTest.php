<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRemarkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin', 'status' => 'active', 'permissions' => ['bookings'],
        ]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function booking(string $status = 'pending_verification', array $extra = []): Booking
    {
        static $n = 0;
        $n++;

        $provider = Provider::create(['name' => "Provider {$n}", 'type' => 'local_operator', 'status' => 'active']);
        $package = Package::create([
            'code' => "PKG-R{$n}", 'title' => "Package R{$n}", 'slug' => "package-r-{$n}",
            'category' => 'domestic', 'status' => 'active', 'provider_id' => $provider->id,
        ]);
        $customer = Customer::create(['name' => "Customer R{$n}", 'agent_id' => $this->agent->id]);

        return Booking::create([
            'booking_no'  => "BK-REM-{$n}",
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $this->agent->id,
            'provider_id' => $provider->id,
            'status'      => $status,
            'notes'       => 'Customer asked for a late check-in.',
            'agent_note'  => 'Agent wants admin to know this.',
        ] + $extra);
    }

    public function test_staff_can_add_a_remark_from_the_list_without_opening_the_booking(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.remark', $booking), ['internal_notes' => 'Call customer Friday.'])
            ->assertSessionHas('ok')
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('Call customer Friday.', $booking->internal_notes);
    }

    public function test_saving_a_remark_leaves_customer_and_agent_fields_alone(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.remark', $booking), ['internal_notes' => 'Staff only.']);

        $booking->refresh();
        $this->assertSame('Customer asked for a late check-in.', $booking->notes);
        $this->assertSame('Agent wants admin to know this.', $booking->agent_note);
        $this->assertSame('pending_verification', $booking->status);
    }

    public function test_editing_replaces_the_remark_and_empty_clears_it(): void
    {
        $booking = $this->booking(extra: ['internal_notes' => 'Old remark']);

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.remark', $booking), ['internal_notes' => 'New remark']);
        $this->assertSame('New remark', $booking->refresh()->internal_notes);

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.remark', $booking), ['internal_notes' => '   ']);
        $this->assertNull($booking->refresh()->internal_notes);
    }

    public function test_remark_is_capped_at_1000_characters(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.remark', $booking), ['internal_notes' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('internal_notes');

        $this->assertNull($booking->refresh()->internal_notes);
    }

    public function test_the_bookings_list_shows_the_remark_inline(): void
    {
        $this->booking(extra: ['internal_notes' => 'Visible in the list']);

        $this->actingAs($this->admin)
            ->get(route('manage.bookings.index'))
            ->assertOk()
            ->assertSee('Visible in the list', false)
            ->assertSee('name="internal_notes"', false);
    }

    public function test_the_remark_is_not_shown_to_the_agent(): void
    {
        $booking = $this->booking(extra: ['internal_notes' => 'Secret staff remark']);

        $this->actingAs($this->agent)
            ->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Secret staff remark');
    }
}
