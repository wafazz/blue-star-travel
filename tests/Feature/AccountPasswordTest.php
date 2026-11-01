<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => $role, 'status' => 'active', 'password' => Hash::make('OldPass123!'),
            'permissions' => $role === 'admin' ? ['bookings'] : null,
        ], $extra));

        if ($role === 'customer') {
            Customer::create(['user_id' => $user->id, 'name' => $user->name, 'phone' => '0111']);
        }

        return $user;
    }

    public function test_every_account_role_can_open_the_password_page(): void
    {
        foreach (['customer', 'agent', 'admin', 'hq', 'super_admin'] as $role) {
            $this->actingAs($this->user($role))->get(route('customer.password.edit'))
                ->assertOk()
                ->assertSee('Change password')
                ->assertSee('data-target="password"', false)
                ->assertSee('pw_bar', false);
        }
    }

    public function test_providers_cannot_open_the_password_page(): void
    {
        $this->actingAs($this->user('provider'))->get(route('customer.password.edit'))->assertForbidden();
    }

    public function test_user_changes_password_with_the_current_one(): void
    {
        $user = $this->user('agent');

        $this->actingAs($user)->put(route('customer.password.update'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456#',
            'password_confirmation' => 'NewPass456#',
        ])->assertSessionHas('ok');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass456#', $user->password));
        $this->assertFalse(Hash::check('OldPass123!', $user->password));
    }

    public function test_wrong_current_password_is_refused(): void
    {
        $user = $this->user('admin');

        $this->actingAs($user)->put(route('customer.password.update'), [
            'current_password' => 'not-it',
            'password' => 'NewPass456#',
            'password_confirmation' => 'NewPass456#',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPass123!', $user->refresh()->password));
    }

    public function test_confirmation_must_match(): void
    {
        $this->actingAs($this->user('customer'))->put(route('customer.password.update'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456#',
            'password_confirmation' => 'Different789#',
        ])->assertSessionHasErrors('password');
    }

    public function test_new_password_needs_at_least_8_characters(): void
    {
        $this->actingAs($this->user('hq'))->put(route('customer.password.update'), [
            'current_password' => 'OldPass123!',
            'password' => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ])->assertSessionHasErrors('password');
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        $this->actingAs($this->user('super_admin'))->put(route('customer.password.update'), [
            'current_password' => 'OldPass123!',
            'password' => 'OldPass123!',
            'password_confirmation' => 'OldPass123!',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_change_does_not_touch_email_or_profile(): void
    {
        $user = $this->user('agent', ['email' => 'keep@example.test', 'name' => 'Keep Me']);

        $this->actingAs($user)->put(route('customer.password.update'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456#',
            'password_confirmation' => 'NewPass456#',
        ]);

        $user->refresh();
        $this->assertSame('keep@example.test', $user->email);
        $this->assertSame('Keep Me', $user->name);
    }
}
