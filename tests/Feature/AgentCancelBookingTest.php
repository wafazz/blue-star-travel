<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\Provider;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The agent may cancel a booking; only HQ may pay anything back.
 */
class AgentCancelBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $otherAgent;
    private User $hq;
    private User $admin;
    private Package $package;
    private PackagePricing $tier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->otherAgent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);
        $this->admin = User::factory()->create([
            'role' => 'admin', 'status' => 'active', 'permissions' => ['bookings', 'finance'],
        ]);

        $provider = Provider::create(['name' => 'Redang Ops', 'type' => 'local_operator', 'status' => 'active']);
        $this->package = Package::create([
            'code' => 'PKG-RDG', 'title' => 'Pulau Redang 4D3N', 'slug' => 'pulau-redang-4d3n',
            'category' => 'domestic', 'status' => 'active', 'provider_id' => $provider->id, 'date_mode' => 'open',
        ]);
        $this->tier = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Standard', 'capacity' => 4,
            'adult_price' => 1000, 'child_price' => 700, 'senior_price' => 1000, 'infant_price' => 0,
            'is_default' => true,
        ]);
    }

    private function booking(array $extra = []): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Puan Siti', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);

        $booking = Booking::create($extra + [
            'booking_no' => "BK-CAN-{$n}", 'package_id' => $this->package->id,
            'customer_id' => $customer->id, 'agent_id' => $this->agent->id,
            'status' => 'pending_verification', 'travel_date' => '2026-09-14',
            'adults' => 6, 'children' => 3, 'seniors' => 1, 'infants' => 2, 'total_pax' => 12,
            'subtotal' => 9100, 'total_amount' => 9100, 'paid_amount' => 3000,
        ]);

        $booking->rooms()->create([
            'package_pricing_id' => $this->tier->id, 'room_name' => 'Standard', 'rooms' => 3,
            'adults' => 6, 'children' => 3, 'seniors' => 1, 'infants' => 2,
            'adult_price' => 1000, 'child_price' => 700, 'senior_price' => 1000, 'infant_price' => 0,
            'subtotal' => 9100,
        ]);

        return $booking->refresh();
    }

    private function cancel(Booking $booking, array $data = [])
    {
        return $this->actingAs($this->agent)->post(route('agent.bookings.cancel', $booking), $data + [
            'confirm' => 'CANCEL',
            'reason'  => 'Customer changed plans',
        ]);
    }

    public function test_an_agent_cancels_and_every_remaining_pack_is_charged(): void
    {
        $booking = $this->booking();

        $this->cancel($booking)->assertRedirect(route('agent.bookings.show', $booking));

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame(10, (int) $booking->forfeited_packs);
        $this->assertSame(1000.0, (float) $booking->forfeited_amount);
        $this->assertSame(2000.0, $booking->refundableAmount());
        $this->assertSame(0.0, $booking->balance());
    }

    public function test_the_typed_confirmation_is_enforced_server_side(): void
    {
        $booking = $this->booking();

        $this->cancel($booking, ['confirm' => 'yes'])->assertSessionHasErrors('confirm');
        $this->cancel($booking, ['confirm' => ''])->assertSessionHasErrors('confirm');

        $this->assertSame('pending_verification', $booking->refresh()->status);
        $this->assertSame(0.0, (float) $booking->forfeited_amount);
    }

    public function test_a_reason_is_required(): void
    {
        $booking = $this->booking();

        $this->cancel($booking, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending_verification', $booking->refresh()->status);
    }

    public function test_an_agent_cannot_cancel_someone_elses_booking(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->otherAgent)
            ->post(route('agent.bookings.cancel', $booking), ['confirm' => 'CANCEL', 'reason' => 'nope'])
            ->assertForbidden();

        $this->assertSame('pending_verification', $booking->refresh()->status);
    }

    public function test_a_finished_booking_can_no_longer_be_cancelled(): void
    {
        $booking = $this->booking(['status' => 'completed']);

        $this->cancel($booking)->assertSessionHasErrors('status');
        $this->assertSame('completed', $booking->refresh()->status);
    }

    public function test_cancelling_twice_does_not_charge_twice(): void
    {
        $booking = $this->booking();

        $this->cancel($booking);
        $this->cancel($booking)->assertSessionHasErrors('status');

        $booking->refresh();
        $this->assertSame(10, (int) $booking->forfeited_packs);
        $this->assertSame(1000.0, (float) $booking->forfeited_amount);
    }

    public function test_hq_is_notified_that_a_refund_is_owed(): void
    {
        $booking = $this->booking();

        $this->cancel($booking);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->hq->id,
            'title'   => "Refund due on {$booking->booking_no}: RM 2,000.00",
        ]);
        // The agent who pressed the button does not get the HQ action notice.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->agent->id,
            'title'   => "Refund due on {$booking->booking_no}: RM 2,000.00",
        ]);
    }

    public function test_the_cancel_card_shows_the_charge_before_it_is_pressed(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Cancel Booking')
            ->assertSee('RM 1,000.00')          // 10 packs × RM100
            ->assertSee('HQ processes any refund', false);

        $this->assertSame('pending_verification', $booking->refresh()->status);
    }

    public function test_an_agent_has_no_way_to_refund(): void
    {
        $booking = $this->booking();
        $this->cancel($booking);

        $this->actingAs($this->agent)
            ->post(route('manage.bookings.refund', $booking), ['amount' => 2000, 'method' => 'bank_transfer'])
            ->assertForbidden();

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_refunds_are_hq_only_not_admin(): void
    {
        $booking = $this->booking();
        $this->cancel($booking);

        // An admin with the finance permission still may not move money out.
        $this->actingAs($this->admin)->get(route('manage.finance.refunds'))->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('manage.bookings.refund', $booking), ['amount' => 2000, 'method' => 'bank_transfer'])
            ->assertForbidden();

        $this->actingAs($this->hq)->get(route('manage.finance.refunds'))->assertOk();
        $this->actingAs($this->hq)
            ->post(route('manage.bookings.refund', $booking), ['amount' => 2000, 'method' => 'bank_transfer'])
            ->assertRedirect();

        $this->assertSame(2000.0, (float) Refund::first()->amount);
    }

    public function test_the_refund_button_is_hidden_from_admin_staff(): void
    {
        $booking = $this->booking();
        $this->cancel($booking);

        $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Refunds are handled by HQ')
            ->assertDontSee('Request Refund');

        $this->actingAs($this->hq)->get(route('manage.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Request Refund');
    }
}
