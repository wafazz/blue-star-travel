<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /account/profile for agents and staff: name, phone and title are editable; email is not. */
class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        return User::factory()->create([
            'role' => 'agent', 'status' => 'active', 'name' => 'Agent One', 'email' => 'agent1@example.test',
            'phone' => '011', 'title' => 'Sales Executive', 'agent_code' => 'BT-AG0001',
        ]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => 'active', 'name' => 'Staff One', 'email' => "{$role}1@example.test",
            'permissions' => $role === 'admin' ? ['bookings'] : null,
        ]);
    }

    public function test_agent_sees_and_edits_their_profile(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('agent1@example.test')
            ->assertSee('BT-AG0001')
            ->assertSee('Sales Executive');

        $this->actingAs($agent)->put(route('customer.profile.update'), [
            'name' => 'Agent Renamed', 'phone' => '012 345', 'title' => 'Senior Sales Executive',
        ])->assertSessionHas('ok');

        $agent->refresh();
        $this->assertSame('Agent Renamed', $agent->name);
        $this->assertSame('012 345', $agent->phone);
        $this->assertSame('Senior Sales Executive', $agent->title);
        $this->assertSame('agent1@example.test', $agent->email);
    }

    public function test_agent_cannot_change_email_on_profile(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put(route('customer.profile.update'), [
            'name' => 'Agent One', 'email' => 'evil@example.test',
        ])->assertSessionHasErrors('email');

        $this->assertSame('agent1@example.test', $agent->refresh()->email);
    }

    public function test_agent_name_is_required(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put(route('customer.profile.update'), ['name' => '', 'phone' => '011'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_staff_see_and_edit_their_profile(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('admin1@example.test');

        $this->actingAs($admin)->put(route('customer.profile.update'), [
            'name' => 'Admin Renamed', 'phone' => '013', 'title' => 'Ops Admin',
        ])->assertSessionHas('ok');

        $admin->refresh();
        $this->assertSame('Admin Renamed', $admin->name);
        $this->assertSame('Ops Admin', $admin->title);
        $this->assertSame('admin1@example.test', $admin->email);
    }

    public function test_hq_and_super_admin_can_open_their_profile(): void
    {
        foreach (['hq', 'super_admin'] as $role) {
            $this->actingAs($this->staff($role))->get(route('customer.profile.edit'))->assertOk();
        }
    }

    public function test_staff_cannot_change_email_on_profile(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->put(route('customer.profile.update'), [
            'name' => 'Admin One', 'email' => 'evil@example.test',
        ])->assertSessionHasErrors('email');

        $this->assertSame('admin1@example.test', $admin->refresh()->email);
    }

    public function test_providers_still_cannot_open_the_profile(): void
    {
        $provider = User::factory()->create(['role' => 'provider', 'status' => 'active']);

        $this->actingAs($provider)->get(route('customer.profile.edit'))->assertForbidden();
    }

    public function test_card_pages_point_name_edits_to_the_profile(): void
    {
        $agent = $this->agent();
        $admin = $this->staff('admin');

        $this->actingAs($agent)->get(route('agent.card'))
            ->assertSee(route('customer.profile.edit'), false);
        $this->actingAs($admin)->get(route('manage.card'))
            ->assertSee(route('customer.profile.edit'), false);
    }

    public function test_agent_and_staff_profiles_save_the_address(): void
    {
        $agent = $this->agent();
        $admin = $this->staff('admin');

        $this->actingAs($agent)->put(route('customer.profile.update'), [
            'name' => 'Agent One', 'address' => 'PT384, Lembah Sireh, Kota Bharu',
        ])->assertSessionHasNoErrors();
        $this->assertSame('PT384, Lembah Sireh, Kota Bharu', $agent->refresh()->address);

        $this->actingAs($admin)->get(route('customer.profile.edit'))->assertOk()->assertSee('Office address');
        $this->actingAs($admin)->put(route('customer.profile.update'), [
            'name' => 'Staff One', 'address' => 'Head office, Kuala Lumpur',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Head office, Kuala Lumpur', $admin->refresh()->address);
    }
}
