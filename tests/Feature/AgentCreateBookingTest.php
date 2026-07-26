<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCreateBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Package $package;
    private PackagePricing $quad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'open',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500,
            'infant_price' => 0, 'is_default' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'package_id'  => $this->package->id,
            'type'        => 'online',
            'travel_date' => now()->addMonths(2)->format('Y-m-d'),
            'rooms' => [['package_pricing_id' => $this->quad->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
        ];
    }

    /**
     * The "➕ New customer…" option posts `__new` rather than "" so Select2 keeps it in
     * the list — Select2 drops every empty-value option as a placeholder.
     */
    public function test_the_new_customer_sentinel_registers_a_customer_instead_of_failing_validation(): void
    {
        $response = $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id'        => '__new',
            'new_customer_name'  => 'Nur Aina Binti Rahim',
            'new_customer_phone' => '0123456789',
            'new_customer_email' => 'aina@example.com',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Nur Aina Binti Rahim')->first();
        $this->assertNotNull($customer, '`__new` must not reach the exists: rule.');
        $this->assertSame($this->agent->id, $customer->agent_id, 'A self-registered customer belongs to the agent.');
        $this->assertSame('0123456789', $customer->phone);

        $booking = Booking::first();
        $this->assertSame($customer->id, $booking->customer_id);
    }

    public function test_picking_an_existing_customer_still_works(): void
    {
        $customer = Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $customer->id,
        ]))->assertRedirect();

        $this->assertSame(1, Customer::count(), 'No duplicate customer may be created.');
        $this->assertSame($customer->id, Booking::first()->customer_id);
    }

    public function test_the_new_customer_option_is_offered_on_the_form(): void
    {
        Customer::create(['name' => 'Existing', 'phone' => '0111111111', 'agent_id' => $this->agent->id]);

        $this->actingAs($this->agent)->get(route('agent.bookings.create'))
            ->assertOk()
            ->assertSee('New customer', false)
            ->assertSee('value="__new"', false);
    }

    public function test_a_new_customer_still_needs_a_name_and_phone(): void
    {
        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => '__new',
        ]))->assertSessionHasErrors('customer_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_an_agent_cannot_book_another_agents_customer(): void
    {
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $theirs = Customer::create(['name' => 'Not Mine', 'phone' => '0199999999', 'agent_id' => $other->id]);

        $this->actingAs($this->agent)->post(route('agent.bookings.store'), $this->payload([
            'customer_id' => $theirs->id,
        ]))->assertForbidden();
    }
}
