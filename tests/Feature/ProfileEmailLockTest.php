<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The login email is changed by HQ only. No profile update may change it, and a submitted
 * email is rejected (not silently dropped), so a tampered form cannot pretend to succeed.
 */
class ProfileEmailLockTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create([
            'role' => 'customer', 'status' => 'active', 'name' => 'Cust', 'email' => 'cust@example.test', 'phone' => '0111',
        ]);
        Customer::create(['user_id' => $user->id, 'name' => 'Cust', 'phone' => '0111']);

        return $user;
    }

    private function staff(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role, 'status' => 'active', 'email' => "{$role}@example.test",
            'permissions' => $role === 'admin' ? ['bookings'] : null,
        ], $extra));
    }

    public function test_customer_profile_shows_email_read_only(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('cust@example.test')
            ->assertSee('changed by HQ only', false);
    }

    public function test_customer_cannot_change_email_on_profile(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'Cust New', 'phone' => '0122', 'email' => 'hacked@example.test',
        ])->assertSessionHasErrors('email');

        $customer->refresh();
        $this->assertSame('cust@example.test', $customer->email);
        $this->assertSame('Cust', $customer->name, 'a rejected request changes nothing');
    }

    public function test_customer_profile_saves_without_touching_email(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'Cust New', 'phone' => '0122',
        ])->assertSessionHas('ok');

        $customer->refresh();
        $this->assertSame('cust@example.test', $customer->email);
        $this->assertSame('Cust New', $customer->name);
    }

    public function test_agent_cannot_change_email_on_card(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active', 'email' => 'ag@example.test', 'agent_code' => 'BT-AG0001']);

        $this->actingAs($agent)->put(route('agent.card.update'), [
            'title' => 'Hacked', 'email' => 'evil@example.test',
        ])->assertSessionHasErrors('email');

        $agent->refresh();
        $this->assertSame('ag@example.test', $agent->email);
        $this->assertNull($agent->title);
    }

    public function test_staff_cannot_change_email_on_their_card(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->put(route('manage.card.update'), [
            'title' => 'Hacked', 'email' => 'evil@example.test',
        ])->assertSessionHasErrors('email');

        $admin->refresh();
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertNull($admin->title);
    }

    public function test_staff_card_saves_title_and_phone_without_email(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->put(route('manage.card.update'), ['title' => 'Ops Lead', 'phone' => '013'])
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Ops Lead', $admin->title);
        $this->assertSame('admin@example.test', $admin->email);
    }

    public function test_hq_cannot_change_their_own_email_from_the_staff_form(): void
    {
        $hq = $this->staff('hq');

        $this->actingAs($hq)->put(route('manage.staff.update', $hq), [
            'name' => 'HQ Renamed', 'email' => 'new@example.test', 'role' => 'hq', 'status' => 'active',
        ])->assertSessionHasErrors('email');

        $hq->refresh();
        $this->assertSame('hq@example.test', $hq->email);
    }

    public function test_hq_can_still_save_their_own_name_without_an_email(): void
    {
        $hq = $this->staff('hq');

        // The form disables the email input for self, so the request omits it.
        $this->actingAs($hq)->put(route('manage.staff.update', $hq), [
            'name' => 'HQ Renamed', 'role' => 'hq', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $hq->refresh();
        $this->assertSame('HQ Renamed', $hq->name);
        $this->assertSame('hq@example.test', $hq->email);
    }

    public function test_hq_can_still_change_another_staff_members_email(): void
    {
        $hq = $this->staff('hq');
        $admin = $this->staff('admin');

        $this->actingAs($hq)->put(route('manage.staff.update', $admin), [
            'name' => 'Admin', 'email' => 'admin.new@example.test', 'role' => 'admin', 'status' => 'active',
            'permissions' => ['bookings'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('admin.new@example.test', $admin->refresh()->email);
    }
}
