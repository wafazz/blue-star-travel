<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\Provider;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reducing the pack count on a booking money has been taken on burns the deposit at
 * RM x per cancelled pack. A pack is one adult, child or senior — never an infant.
 */
class BookingForfeitureTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Package $package;
    private PackagePricing $tier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

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

    /** 6 adults + 3 children + 1 senior = 10 packs, plus 2 infants riding free. RM 9,100. */
    private function booking(array $extra = []): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Puan Siti', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);

        // $extra first — array + keeps the LEFT operand on a key collision.
        $booking = Booking::create($extra + [
            'booking_no' => "BK-FRF-{$n}", 'package_id' => $this->package->id,
            'customer_id' => $customer->id, 'agent_id' => $this->agent->id,
            'status' => 'pending_verification', 'travel_date' => '2026-09-14',
            'adults' => 6, 'children' => 3, 'seniors' => 1, 'infants' => 2, 'total_pax' => 12,
            'subtotal' => 9100, 'total_amount' => 9100, 'paid_amount' => 3000,
        ] + $extra);

        $booking->rooms()->create([
            'package_pricing_id' => $this->tier->id, 'room_name' => 'Standard', 'rooms' => 3,
            'adults' => 6, 'children' => 3, 'seniors' => 1, 'infants' => 2,
            'adult_price' => 1000, 'child_price' => 700, 'senior_price' => 1000, 'infant_price' => 0,
            'subtotal' => 9100,
        ]);
        $booking->pax()->create(['name' => 'Puan Siti', 'type' => 'adult', 'is_lead' => true]);

        return $booking->refresh();
    }

    private function payload(array $room): array
    {
        return [
            'customer_name'  => 'Puan Siti',
            'customer_phone' => '0123456789',
            'package_id'     => $this->package->id,
            'travel_date'    => '2026-09-14',
            'rooms'          => [$room + ['package_pricing_id' => $this->tier->id]],
            'pax'            => [['name' => 'Puan Siti', 'type' => 'adult', 'is_lead' => 1]],
        ];
    }

    /** Stage the edit, then resubmit it — the two steps the agent actually goes through. */
    private function reduceTo(Booking $booking, array $room): void
    {
        $this->actingAs($this->agent)
            ->post(route('agent.bookings.review', $booking), $this->payload($room))
            ->assertRedirect();

        $this->actingAs($this->agent)
            ->post(route('agent.bookings.resubmit', $booking), ['confirm' => '1'])
            ->assertRedirect();

        $booking->refresh();
    }

    public function test_dropping_ten_packs_to_seven_burns_rm100_each_out_of_what_was_paid(): void
    {
        $booking = $this->booking();

        // 4 adults + 2 children + 1 senior = 7 packs. The 2 infants stay.
        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);

        $this->assertSame(3, (int) $booking->forfeited_packs);
        $this->assertSame(300.0, (float) $booking->forfeited_amount);

        // The trip re-prices to 4×1000 + 2×700 + 1×1000. The penalty never enters it.
        $this->assertSame(6400.0, (float) $booking->subtotal);
        $this->assertSame(6400.0, (float) $booking->total_amount);

        // RM300 is eaten out of the RM3,000 deposit, so only RM2,700 works for them.
        $this->assertSame(2700.0, $booking->paidAfterForfeit());
        $this->assertSame(3700.0, $booking->balance());
    }

    public function test_the_burn_is_recorded_on_the_timeline_and_the_agent_is_told(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);

        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => '3 pack(s) cancelled — RM 300.00 deposit forfeited',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agent->id,
            'title'   => "Deposit forfeited on {$booking->booking_no}",
        ]);
    }

    public function test_successive_reductions_accrue_instead_of_being_recomputed(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]); // 10 → 7
        $this->reduceTo($booking, ['adults' => 3, 'children' => 1, 'seniors' => 1, 'infants' => 2]); // 7 → 5

        $this->assertSame(5, (int) $booking->forfeited_packs);
        $this->assertSame(500.0, (float) $booking->forfeited_amount);

        // 3×1000 + 1×700 + 1×1000 of travel; RM500 burnt across both edits leaves
        // RM2,500 of the deposit still working.
        $this->assertSame(4700.0, (float) $booking->subtotal);
        $this->assertSame(4700.0, (float) $booking->total_amount);
        $this->assertSame(2500.0, $booking->paidAfterForfeit());
        $this->assertSame(2200.0, $booking->balance());
    }

    public function test_removing_an_infant_costs_nothing(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 6, 'children' => 3, 'seniors' => 1, 'infants' => 0]);

        $this->assertSame(0, (int) $booking->forfeited_packs);
        $this->assertSame(0.0, (float) $booking->forfeited_amount);
        $this->assertSame(9100.0, (float) $booking->total_amount);
    }

    public function test_adding_packs_is_never_penalised(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 8, 'children' => 3, 'seniors' => 1, 'infants' => 2]);

        $this->assertSame(0.0, (float) $booking->forfeited_amount);
        $this->assertSame(11100.0, (float) $booking->total_amount);
        $this->assertSame(8100.0, $booking->balance());
    }

    public function test_an_unpaid_booking_can_be_reduced_for_free(): void
    {
        $booking = $this->booking(['paid_amount' => 0]);

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);

        // Nothing has been paid, so there is no deposit to burn — a typo fix stays free.
        $this->assertSame(0.0, (float) $booking->forfeited_amount);
        $this->assertSame(6400.0, (float) $booking->total_amount);
    }

    public function test_a_reduction_below_what_was_paid_refunds_paid_minus_the_charge(): void
    {
        $booking = $this->booking(['paid_amount' => 9100]);

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);

        // Paid 9,100 − 300 charge = 8,800 usable, against a 6,400 trip.
        $this->assertSame(6400.0, (float) $booking->total_amount);
        $this->assertSame(8800.0, $booking->paidAfterForfeit());
        $this->assertSame(2400.0, $booking->refundableAmount());
        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Refundable: RM 2,400.00',
        ]);
    }

    public function test_the_package_rate_overrides_the_house_rate_and_zero_waives_it(): void
    {
        $this->package->update(['cancellation_fee_per_pack' => 250]);
        $booking = $this->booking();
        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);
        $this->assertSame(750.0, (float) $booking->forfeited_amount);

        $this->package->update(['cancellation_fee_per_pack' => 0]);
        $waived = $this->booking();
        $this->reduceTo($waived, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);
        $this->assertSame(0.0, (float) $waived->forfeited_amount);
        $this->assertSame(6400.0, (float) $waived->total_amount);
    }

    public function test_commission_is_earned_on_travel_value_not_on_the_penalty(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]);

        // Commission reads total_amount, which is the trip alone — the RM300 penalty
        // is company revenue and must never widen the agent's base.
        $this->assertSame(6400.0, (float) $booking->total_amount);
        $this->assertSame(300.0, (float) $booking->forfeited_amount);
    }

    public function test_the_review_screen_warns_before_the_agent_commits(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->post(route('agent.bookings.review', $booking),
            $this->payload(['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]));

        $this->actingAs($this->agent)->get(route('agent.bookings.review.show', $booking))
            ->assertOk()
            ->assertSee('Cancellation Charge')
            ->assertSee('RM 300.00')
            ->assertSee('RM 6,400.00')   // new trip total
            ->assertSee('RM 3,700.00');  // balance to pay after the charge

        // Still only staged — nothing may have moved on the live booking yet.
        $this->assertSame(0.0, (float) $booking->refresh()->forfeited_amount);
        $this->assertSame(9100.0, (float) $booking->total_amount);
    }

    public function test_cancelling_outright_burns_every_pack_and_refunds_the_rest(): void
    {
        $booking = $this->booking();

        app(BookingService::class)->cancel($booking, $this->agent, 'Customer changed plans');
        $booking->refresh();

        // All 10 packs go, infants still free.
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame(10, (int) $booking->forfeited_packs);
        $this->assertSame(1000.0, (float) $booking->forfeited_amount);

        // Refund = paid − cancellation charge. The trip is off, so nothing is owed —
        // a cancelled booking must never keep reporting an outstanding balance, or it
        // inflates the finance dashboard's outstanding total.
        $this->assertSame(2000.0, $booking->refundableAmount());
        $this->assertSame(0.0, $booking->balance());

        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => '10 pack(s) cancelled — RM 1,000.00 deposit forfeited',
        ]);
        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Refundable after cancellation charge: RM 2,000.00',
        ]);
    }

    public function test_cancelling_after_a_reduction_only_charges_the_packs_still_left(): void
    {
        $booking = $this->booking();

        $this->reduceTo($booking, ['adults' => 4, 'children' => 2, 'seniors' => 1, 'infants' => 2]); // 10 → 7
        app(BookingService::class)->cancel($booking, $this->agent, 'Cancelled after downsizing');
        $booking->refresh();

        // 3 packs on the reduction + the 7 that were left = 10, never double-charged.
        $this->assertSame(10, (int) $booking->forfeited_packs);
        $this->assertSame(1000.0, (float) $booking->forfeited_amount);
        $this->assertSame(2000.0, $booking->refundableAmount());
    }

    public function test_cancelling_an_unpaid_booking_costs_nothing(): void
    {
        $booking = $this->booking(['paid_amount' => 0]);

        app(BookingService::class)->cancel($booking, $this->agent, null);
        $booking->refresh();

        $this->assertSame(0, (int) $booking->forfeited_packs);
        $this->assertSame(0.0, (float) $booking->forfeited_amount);
        $this->assertSame(0.0, $booking->refundableAmount());
    }

    public function test_a_cancellation_charge_larger_than_the_deposit_leaves_nothing_to_refund(): void
    {
        // RM500 paid against 10 packs = RM1,000 of penalty.
        $booking = $this->booking(['paid_amount' => 500]);

        app(BookingService::class)->cancel($booking, $this->agent, null);
        $booking->refresh();

        $this->assertSame(1000.0, (float) $booking->forfeited_amount);
        $this->assertSame(0.0, $booking->refundableAmount());
        $this->assertSame(-500.0, $booking->paidAfterForfeit());
    }

    public function test_an_already_refunded_amount_is_not_offered_twice(): void
    {
        $booking = $this->booking();

        app(BookingService::class)->cancel($booking, $this->agent, null);
        $booking->refresh();
        $this->assertSame(2000.0, $booking->refundableAmount());

        $booking->refunds()->create([
            'refund_no' => 'RF-TEST-1', 'amount' => 1200, 'method' => 'bank_transfer', 'status' => 'processed',
        ]);

        $this->assertSame(800.0, $booking->refresh()->refundableAmount());
    }
}
