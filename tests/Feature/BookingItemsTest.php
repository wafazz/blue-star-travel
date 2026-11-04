<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\User;
use App\Services\BookingItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-I', 'title' => 'Langkawi 3D', 'slug' => 'langkawi-3d',
            'category' => 'cruise', 'status' => 'active', 'date_mode' => 'open',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 1000, 'child_price' => 800, 'senior_price' => 1000, 'infant_price' => 0, 'is_default' => true,
        ]);
    }

    /** 2 adults at RM1,000 each: subtotal RM2,000, total RM2,000, RM2,000 paid. */
    private function booking(string $status = 'confirmed', float $discount = 0): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Nur Aina', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);
        $booking = Booking::create([
            'booking_no' => "BK-ITM-{$n}", 'package_id' => $this->package->id, 'customer_id' => $customer->id,
            'agent_id' => $this->agent->id, 'status' => $status, 'travel_date' => '2026-12-01',
            'adults' => 2, 'total_pax' => 2, 'subtotal' => 2000, 'discount' => $discount,
            'total_amount' => 2000 - $discount, 'paid_amount' => 2000 - $discount,
        ]);
        $booking->rooms()->create([
            'package_pricing_id' => $this->quad->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 1000, 'child_price' => 800, 'senior_price' => 1000, 'infant_price' => 0,
            'subtotal' => 2000,
        ]);
        $booking->pax()->create(['name' => 'Nur Aina', 'type' => 'adult', 'is_lead' => true]);

        return $booking;
    }

    private function addItem(Booking $booking, array $data)
    {
        return $this->actingAs($this->staff)->post(route('manage.bookings.items.store', $booking), $data + [
            'name' => 'Snorkelling', 'unit_amount' => 150, 'included' => 1,
        ]);
    }

    public function test_an_included_item_adds_its_amount_per_pax_to_the_total(): void
    {
        $booking = $this->booking();

        $this->addItem($booking, ['unit_amount' => 300])->assertRedirect();

        $booking->refresh();
        $this->assertSame(2600.0, (float) $booking->total_amount, 'RM300 × 2 pax on top of RM2,000.');
        $this->assertSame(2000.0, (float) $booking->paid_amount, 'The deposit never moves.');
        $this->assertSame(600.0, $booking->balance());
    }

    public function test_an_excluded_item_is_recorded_but_leaves_the_total_alone(): void
    {
        $booking = $this->booking();

        $this->addItem($booking, ['unit_amount' => 300, 'included' => 0])->assertRedirect();

        $this->assertSame(1, $booking->items()->count());
        $this->assertSame(2000.0, (float) $booking->refresh()->total_amount);
    }

    public function test_toggling_an_item_moves_the_total_both_ways(): void
    {
        $booking = $this->booking();
        $this->addItem($booking, ['unit_amount' => 300, 'included' => 0]);
        $item = $booking->items()->first();

        $this->actingAs($this->staff)->patch(route('manage.bookings.items.update', [$booking, $item]), ['included' => 1]);
        $this->assertSame(2600.0, (float) $booking->refresh()->total_amount);

        $this->actingAs($this->staff)->patch(route('manage.bookings.items.update', [$booking, $item]), ['included' => 0]);
        $this->assertSame(2000.0, (float) $booking->refresh()->total_amount);
    }

    public function test_a_deduction_reduces_the_total_and_removing_an_item_restores_it(): void
    {
        $booking = $this->booking();
        $this->addItem($booking, ['name' => 'Early-bird discount', 'unit_amount' => 100, 'direction' => 'deduct']);
        $this->assertSame(1800.0, (float) $booking->refresh()->total_amount);

        $item = $booking->items()->first();
        $this->actingAs($this->staff)->delete(route('manage.bookings.items.destroy', [$booking, $item]));

        $this->assertSame(0, $booking->items()->count());
        $this->assertSame(2000.0, (float) $booking->refresh()->total_amount);
    }

    public function test_included_items_raise_the_per_pax_base_a_percentage_commission_uses(): void
    {
        $booking = $this->booking();
        $this->addItem($booking, ['unit_amount' => 150]);
        $this->addItem($booking, ['name' => 'Water taxi', 'unit_amount' => 50, 'included' => 0]);

        $method = new \ReflectionMethod(\App\Services\CommissionService::class, 'paxBreakdown');
        $method->setAccessible(true);
        $pax = $method->invoke(app(\App\Services\CommissionService::class), $booking->refresh());

        $this->assertSame(1150.0, $pax['adult']['price'], 'RM1,000 fare + the included RM150 only.');
        $this->assertSame(0.0, $pax['child']['price'], 'A type with no passengers takes no item amount.');
    }

    public function test_a_total_change_reverses_commission_already_earned(): void
    {
        $booking = $this->booking();
        $booking->update(['agent_id' => $this->agent->id]);
        Commission::create([
            'booking_id' => $booking->id, 'earner_id' => $this->agent->id, 'source_agent_id' => $this->agent->id,
            'level' => 1, 'is_orphan' => false, 'rate_type' => 'percent', 'base_amount' => 2000, 'percent' => 5,
            'amount' => 100, 'status' => 'pending', 'period' => now()->format('Y-m'),
        ]);

        $this->addItem($booking, ['unit_amount' => 300])->assertRedirect();

        $this->assertSame(1, Commission::where('booking_id', $booking->id)->where('status', 'reversed')->count(),
            'A total change reverses commission already earned on the old total.');
    }

    public function test_an_item_on_a_closed_booking_is_refused_and_nothing_moves(): void
    {
        $booking = $this->booking('completed');

        $this->expectException(ValidationException::class);
        app(BookingItemService::class)->add($booking, ['name' => 'Late add', 'unit_amount' => 50], $this->staff);
    }

    public function test_an_item_from_another_booking_is_not_reachable(): void
    {
        $mine = $this->booking();
        $other = $this->booking();
        $this->addItem($other, ['unit_amount' => 300]);
        $item = BookingItem::first();

        $this->actingAs($this->staff)
            ->delete(route('manage.bookings.items.destroy', [$mine, $item]))
            ->assertNotFound();
        $this->assertSame(1, BookingItem::count());
    }

    public function test_a_deduction_for_one_pack_takes_off_only_that_pack(): void
    {
        // RM5,000 for 4 packs. One pack drops scuba diving (RM300): only RM300 comes off.
        $booking = $this->booking();
        $booking->update(['adults' => 4, 'total_pax' => 4, 'subtotal' => 5000, 'total_amount' => 5000, 'paid_amount' => 5000]);
        $booking->rooms()->update(['adults' => 4, 'subtotal' => 5000]);

        $this->addItem($booking, ['name' => 'Scuba diving', 'unit_amount' => 300, 'direction' => 'deduct', 'quantity' => 1])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame(4700.0, (float) $booking->total_amount, 'RM5,000 − RM300 for the one pack.');
        $this->assertSame(5000.0, (float) $booking->paid_amount, 'The deposit never moves.');
        $this->assertSame(-300.0, (float) $booking->items()->first()->unit_amount);
        $this->assertSame(1, $booking->items()->first()->quantity);
    }

    public function test_an_item_with_no_pack_count_applies_to_every_pack(): void
    {
        $booking = $this->booking();
        $booking->update(['adults' => 4, 'total_pax' => 4, 'subtotal' => 5000, 'total_amount' => 5000, 'paid_amount' => 5000]);

        $this->addItem($booking, ['name' => 'Full board', 'unit_amount' => 100, 'direction' => 'add'])->assertRedirect();

        $this->assertSame(5400.0, (float) $booking->refresh()->total_amount, 'RM100 × 4 packs.');
    }

    public function test_the_pack_count_cannot_exceed_the_booking_or_be_zero(): void
    {
        $booking = $this->booking();

        $this->addItem($booking, ['unit_amount' => 300, 'direction' => 'deduct', 'quantity' => 3])->assertSessionHasErrors('quantity');
        $this->addItem($booking, ['unit_amount' => 300, 'direction' => 'deduct', 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->assertSame(0, $booking->items()->count());
    }

    public function test_zero_and_non_numeric_amounts_are_refused(): void
    {
        $booking = $this->booking();

        $this->addItem($booking, ['unit_amount' => 0])->assertSessionHasErrors('unit_amount');
        $this->addItem($booking, ['unit_amount' => 'lots'])->assertSessionHasErrors('unit_amount');
        $this->assertSame(0, $booking->items()->count());
    }
}
