<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCardTest extends TestCase
{
    use RefreshDatabase;

    private function agent(array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create(array_merge([
            'role'       => 'agent',
            'status'     => 'active',
            'name'       => "Shamimie {$n}",
            'email'      => "agent{$n}@example.test",
            'phone'      => '011 2837 9857',
            'agent_code' => sprintf('BT-AG%04d', $n),
            'title'      => 'Sales Executive',
        ], $extra));
    }

    public function test_agent_sees_their_card_with_the_five_fields(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->get(route('agent.card'))
            ->assertOk()
            ->assertSee($agent->name)
            ->assertSee('Sales Executive')
            ->assertSee('011 2837 9857')
            ->assertSee($agent->email)
            ->assertSee($agent->agent_code);
    }

    public function test_agent_can_edit_title_and_phone(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put(route('agent.card.update'), [
            'title' => 'Senior Sales Executive',
            'phone' => '012 000 1111',
        ])->assertSessionHas('ok');

        $agent->refresh();
        $this->assertSame('Senior Sales Executive', $agent->title);
        $this->assertSame('012 000 1111', $agent->phone);
    }

    public function test_name_email_and_member_id_cannot_be_changed_from_the_card(): void
    {
        $agent = $this->agent();
        $originalEmail = $agent->email;
        $originalCode = $agent->agent_code;

        $this->actingAs($agent)->put(route('agent.card.update'), [
            'name'       => 'Hijacked Name',
            'email'      => 'evil@example.test',
            'agent_code' => 'BT-AG9999',
            'role'       => 'admin',
            'title'      => 'Director',
        ]);

        $agent->refresh();
        $this->assertSame('Director', $agent->title);
        $this->assertNotSame('Hijacked Name', $agent->name);
        $this->assertSame($originalEmail, $agent->email);
        $this->assertSame($originalCode, $agent->agent_code);
        $this->assertSame('agent', $agent->role);
    }

    public function test_blank_title_and_phone_clear_the_fields(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put(route('agent.card.update'), ['title' => '  ', 'phone' => ''])
            ->assertSessionHasNoErrors();

        $agent->refresh();
        $this->assertNull($agent->title);
        $this->assertNull($agent->phone);
    }

    public function test_title_is_capped_at_80_characters(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->put(route('agent.card.update'), ['title' => str_repeat('a', 81)])
            ->assertSessionHasErrors('title');
    }

    public function test_public_card_shows_an_active_agent(): void
    {
        $agent = $this->agent();

        $this->get(route('card.public', $agent->agent_code))
            ->assertOk()
            ->assertSee($agent->name)
            ->assertSee('Sales Executive')
            ->assertSee('011 2837 9857')
            ->assertSee($agent->email)
            ->assertSee($agent->agent_code);
    }

    public function test_public_card_is_hidden_for_pending_non_agent_and_unknown_codes(): void
    {
        $pending = $this->agent(['status' => 'pending']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'agent_code' => 'BT-AG7777']);

        $this->get(route('card.public', $pending->agent_code))->assertNotFound();
        $this->get(route('card.public', $customer->agent_code))->assertNotFound();
        $this->get('/card/BT-AG0000')->assertNotFound();
    }

    public function test_public_card_does_not_leak_the_password_hash(): void
    {
        $agent = $this->agent(['password' => bcrypt('super-secret-pass')]);

        $this->get(route('card.public', $agent->agent_code))
            ->assertOk()
            ->assertDontSee($agent->password, false);
    }

    public function test_vcard_downloads_with_escaped_fields(): void
    {
        $agent = $this->agent(['title' => 'Sales, Executive; Lead', 'phone' => '011-2837 9857']);

        $response = $this->get(route('card.vcard', $agent->agent_code));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/vcard; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="' . $agent->agent_code . '.vcf"', $response->headers->get('Content-Disposition'));

        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCARD', $body);
        $this->assertStringContainsString('FN:' . $agent->name, $body);
        $this->assertStringContainsString('TITLE:Sales\, Executive\; Lead', $body);
        $this->assertStringContainsString('TEL;TYPE=CELL:011-2837 9857', $body);
        $this->assertStringContainsString('EMAIL:' . $agent->email, $body);
        $this->assertStringContainsString('NOTE:Member ID ' . $agent->agent_code, $body);
        $this->assertStringContainsString('END:VCARD', $body);
    }

    public function test_vcard_is_hidden_for_inactive_agents(): void
    {
        $agent = $this->agent(['status' => 'suspended']);

        $this->get(route('card.vcard', $agent->agent_code))->assertNotFound();
    }

    public function test_network_page_links_to_the_card(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->get(route('agent.network'))
            ->assertOk()
            ->assertSee(route('agent.card'), false);
    }

    private function staff(string $role = 'admin', array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create(array_merge([
            'role'        => $role,
            'status'      => 'active',
            'name'        => "Staff {$n}",
            'email'       => "staff{$n}@example.test",
            'phone'       => '013 555 000' . $n,
            'title'       => 'Operations Admin',
            'agent_code'  => null,
            'permissions' => $role === 'admin' ? ['bookings'] : null,
        ], $extra));
    }

    public function test_admin_staff_have_a_card_page_with_title_and_phone_editable(): void
    {
        $staff = $this->staff('admin');

        $this->actingAs($staff)->get(route('manage.card'))
            ->assertOk()
            ->assertSee($staff->name)
            ->assertSee('Operations Admin')
            ->assertSee($staff->email)
            ->assertSee(route('card.staff', $staff), false);

        $this->actingAs($staff)->put(route('manage.card.update'), [
            'title' => 'Senior Admin', 'phone' => '013 999 0000', 'name' => 'Hacked', 'email' => 'x@example.test',
        ])->assertSessionHas('ok');

        $staff->refresh();
        $this->assertSame('Senior Admin', $staff->title);
        $this->assertSame('013 999 0000', $staff->phone);
        $this->assertStringStartsWith('Staff ', $staff->name);
        $this->assertStringStartsWith('staff', $staff->email);
    }

    public function test_hq_and_super_admin_also_have_a_card(): void
    {
        foreach (['hq', 'super_admin'] as $role) {
            $staff = $this->staff($role);
            $this->actingAs($staff)->get(route('manage.card'))->assertOk();
        }
    }

    public function test_non_staff_cannot_open_the_manage_card(): void
    {
        $agent = $this->agent();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($agent)->get(route('manage.card'))->assertForbidden();
        $this->actingAs($customer)->get(route('manage.card'))->assertForbidden();
    }

    public function test_public_staff_card_shows_fields_without_a_member_id(): void
    {
        $staff = $this->staff('hq');

        $this->get(route('card.staff', $staff))
            ->assertOk()
            ->assertSee($staff->name)
            ->assertSee('Operations Admin')
            ->assertSee($staff->email)
            ->assertDontSee('Member ID');
    }

    public function test_public_staff_card_is_hidden_for_agents_and_suspended_staff(): void
    {
        $agent = $this->agent();
        $suspended = $this->staff('admin', ['status' => 'suspended']);

        $this->get(route('card.staff', $agent))->assertNotFound();
        $this->get(route('card.staff', $suspended))->assertNotFound();
        $this->get(route('card.staff.vcard', $suspended))->assertNotFound();
    }

    public function test_staff_vcard_has_no_member_id_note(): void
    {
        $staff = $this->staff('admin', ['title' => 'Ops, Lead']);

        $response = $this->get(route('card.staff.vcard', $staff));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('TITLE:Ops\\, Lead', $body);
        $this->assertStringNotContainsString('NOTE:', $body);
        $this->assertStringContainsString('filename="staff-' . $staff->id . '.vcf"', $response->headers->get('Content-Disposition'));
    }

    public function test_every_card_page_has_a_share_button_pointing_at_the_card(): void
    {
        $agent = $this->agent();
        $staff = $this->staff('admin');
        $shareAttr = fn (string $url) => 'data-share-url="' . $url . '"';

        $this->actingAs($agent)->get(route('agent.card'))
            ->assertOk()->assertSee('Share card')
            ->assertSee($shareAttr(route('card.public', $agent->agent_code)), false);

        $this->actingAs($staff)->get(route('manage.card'))
            ->assertOk()->assertSee('Share card')
            ->assertSee($shareAttr(route('card.staff', $staff)), false);

        $this->get(route('card.public', $agent->agent_code))
            ->assertOk()->assertSee('Share card')
            ->assertSee($shareAttr(route('card.public', $agent->agent_code)), false);
    }
}
