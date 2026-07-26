<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\PackagePricing;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\User;
use App\Services\BookingRevisionService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResubmitRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;
    private PackagePricing $triple;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $provider = Provider::create(['name' => 'P', 'type' => 'local_operator', 'status' => 'active']);
        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'provider_id' => $provider->id, 'date_mode' => 'open',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0, 'is_default' => true,
        ]);
        $this->triple = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Triple', 'capacity' => 3,
            'adult_price' => 9434, 'child_price' => 7000, 'senior_price' => 9434, 'infant_price' => 0,
        ]);
    }

    private function booking(array $extra = []): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create([
            'name' => 'Nur Aina', 'phone' => '0123456789', 'email' => 'aina@example.com',
            'agent_id' => $this->agent->id,
        ]);

        $booking = Booking::create([
            'booking_no' => "BK-RSB-{$n}", 'package_id' => $this->package->id,
            'customer_id' => $customer->id, 'agent_id' => $this->agent->id,
            'status' => 'pending_verification', 'travel_date' => '2026-09-14',
            'adults' => 2, 'total_pax' => 2, 'subtotal' => 17000, 'total_amount' => 17000,
        ] + $extra);

        $booking->rooms()->create([
            'package_pricing_id' => $this->quad->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0,
            'subtotal' => 17000,
        ]);
        $booking->pax()->create(['name' => 'Nur Aina', 'type' => 'adult', 'is_lead' => true]);
        $booking->pax()->create(['name' => 'Aliya', 'type' => 'child', 'dob' => '2022-04-12']);

        // Puts the booking in needs_revision and writes the v1 baseline.
        app(BookingService::class)->requestRevision($booking, $this->admin, 'Fix the child age.', ['pax']);

        return $booking->refresh();
    }

    private function payload(Booking $booking, array $overrides = []): array
    {
        $pax = $booking->pax->map(fn ($p) => [
            'key' => (string) $p->id, 'name' => $p->name, 'type' => $p->type,
            'dob' => optional($p->dob)->format('Y-m-d'), 'nationality' => $p->nationality,
        ])->all();

        return $overrides + [
            'customer_name'  => 'Nur Aina',
            'customer_phone' => '0123456789',
            'customer_email' => 'aina@example.com',
            'package_id'     => $this->package->id,
            'travel_date'    => '2026-09-14',
            'rooms' => [['package_pricing_id' => $this->quad->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'pax'   => $pax,
        ];
    }

    private function stage(Booking $booking, array $overrides = [])
    {
        return $this->actingAs($this->agent)
            ->post(route('agent.bookings.review', $booking), $this->payload($booking, $overrides));
    }

    private function resubmit(Booking $booking, array $data = ['confirm' => '1'])
    {
        return $this->actingAs($this->agent)->post(route('agent.bookings.resubmit', $booking), $data);
    }

    public function test_three_changed_fields_produce_exactly_three_diff_rows_in_section_order(): void
    {
        $booking = $this->booking();

        $this->stage($booking, [
            'customer_phone' => '0198887766',                                     // scalar
            'rooms' => [['package_pricing_id' => $this->triple->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]], // room line
            'pax'   => [
                ['key' => (string) $booking->pax[0]->id, 'name' => 'Nur Aina', 'type' => 'adult'],
                ['key' => (string) $booking->pax[1]->id, 'name' => 'Aliya', 'type' => 'child', 'dob' => '2020-04-12'], // passenger
            ],
        ])->assertRedirect(route('agent.bookings.review.show', $booking));

        $response = $this->actingAs($this->agent)->get(route('agent.bookings.review.show', $booking));
        $response->assertOk();

        $rows = collect($response->viewData('rows'));
        $this->assertCount(3, $rows);
        $this->assertSame(['Customer', 'Travel', 'Travel'], $rows->pluck('group')->all());
        $this->assertSame('0123456789', $rows[0]['before']);
        $this->assertSame('0198887766', $rows[0]['after']);
    }

    public function test_an_unchanged_edit_produces_no_diff_rows(): void
    {
        $booking = $this->booking();
        $this->stage($booking);

        $rows = $this->actingAs($this->agent)->get(route('agent.bookings.review.show', $booking))->viewData('rows');

        $this->assertCount(0, $rows);
    }

    public function test_resubmitting_without_the_confirm_checkbox_is_rejected_server_side(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766']);

        $this->resubmit($booking, [])->assertSessionHasErrors('confirm');

        $this->assertSame('needs_revision', $booking->refresh()->status);
        $this->assertDatabaseCount('booking_drafts', 1);
    }

    public function test_a_confirmed_resubmission_applies_the_edit_and_hands_the_booking_back(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766', 'pickup_location' => 'KLIA2 Gate C']);

        // Step 6 of the client flow — the success screen, reached by redirect.
        $this->resubmit($booking)->assertRedirect(route('agent.bookings.submitted', [$booking, 'v' => 2]));

        $booking->refresh();
        $this->assertSame('pending_verification', $booking->status);
        $this->assertSame(1, $booking->revision_no);
        $this->assertNotNull($booking->resubmitted_at);
        $this->assertSame('KLIA2 Gate C', $booking->pickup_location);
        $this->assertSame('0198887766', $booking->customer->phone);

        // v1 initial + v2 revision, and the draft is consumed.
        $this->assertSame(2, $booking->versions()->count());
        $this->assertDatabaseCount('booking_drafts', 0);

        $v2 = $booking->versions()->first();
        $this->assertSame('revision', $v2->reason);
        $this->assertCount(2, $v2->changes);
    }

    public function test_the_open_revision_request_is_resolved_and_the_timeline_links_the_version(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766']);
        $this->resubmit($booking);

        $booking->refresh();
        $this->assertNull($booking->openRevisionRequest);
        $this->assertSame('resolved', $booking->revisionRequests()->first()->status);

        $entry = $booking->timeline()->first();
        $this->assertSame('Agent resubmitted (v2)', $entry->action);
        $this->assertSame($booking->versions()->first()->id, $entry->booking_version_id);
    }

    public function test_rooms_and_pax_are_rebuilt_and_repriced_from_todays_rates(): void
    {
        $booking = $this->booking();

        $this->stage($booking, [
            'rooms' => [['package_pricing_id' => $this->triple->id, 'adults' => 3, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'pax'   => [['key' => 'new-0', 'name' => 'Solo Traveller', 'type' => 'adult']],
        ]);
        $this->resubmit($booking);

        $booking->refresh();
        $this->assertSame(1, $booking->rooms()->count());
        $this->assertSame('Triple', $booking->rooms()->first()->room_name);
        $this->assertSame(3, $booking->adults);
        $this->assertSame(3, $booking->total_pax);
        $this->assertEqualsWithDelta(3 * 9434, (float) $booking->total_amount, 0.01);
        $this->assertSame(1, $booking->pax()->count());
        $this->assertSame('Solo Traveller', $booking->pax()->first()->name);
    }

    public function test_a_resubmit_that_drops_the_total_below_what_was_paid_is_blocked(): void
    {
        $booking = $this->booking(['paid_amount' => 17000]);

        // 2 Quad adults (17,000) → 1 adult (8,500), which is under the 17,000 already paid.
        $this->stage($booking, [
            'rooms' => [['package_pricing_id' => $this->quad->id, 'adults' => 1, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
        ]);

        $this->resubmit($booking)->assertSessionHasErrors('rooms');

        $booking->refresh();
        $this->assertSame('needs_revision', $booking->status, 'A blocked resubmit must not move the booking.');
        $this->assertSame(2, $booking->rooms()->first()->adults, 'Nothing may be applied when the guard trips.');
        $this->assertSame(1, $booking->versions()->count());
    }

    public function test_a_changed_departure_is_re_checked_for_seats(): void
    {
        $this->package->update(['date_mode' => 'fixed']);
        $full = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-12-01',
            'seats_total' => 2, 'seats_booked' => 2, 'status' => 'open',
        ]);
        $booking = $this->booking();

        $this->stage($booking, ['package_date_id' => $full->id, 'travel_date' => '2026-12-01']);

        try {
            app(BookingService::class)->resubmitRevision($booking, $this->agent);
            $this->fail('A full departure must be refused.');
        } catch (ValidationException $e) {
            // Pin the reason — an "add a passenger" error here would be a false pass.
            $this->assertArrayHasKey('package_date_id', $e->errors());
        }
    }

    public function test_a_replaced_receipt_supersedes_the_old_payment_without_touching_its_file(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $old = Payment::create([
            'booking_id' => $booking->id, 'amount' => 2000, 'method' => 'slip_upload',
            'type' => 'deposit', 'status' => 'pending', 'slip_path' => 'payment-slips/original.jpg',
        ]);

        $this->stage($booking, [
            'slip' => UploadedFile::fake()->image('new.jpg'),
            'payment_amount' => '2000.00', 'payment_method' => 'slip_upload',
        ]);
        $this->resubmit($booking);

        $old->refresh();
        $this->assertSame('payment-slips/original.jpg', $old->slip_path, 'The old evidence file must survive.');
        $this->assertNotNull($old->superseded_by);

        $new = Payment::find($old->superseded_by);
        $this->assertNotSame('payment-slips/original.jpg', $new->slip_path);
        $this->assertSame('pending', $new->status);
    }

    public function test_resubmitting_is_refused_once_the_booking_is_finished(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766']);

        // Staff close the booking while the agent sits on the review screen.
        $booking->refresh()->update(['status' => 'completed']);

        $this->resubmit($booking)->assertForbidden();
        $this->assertSame('completed', $booking->refresh()->status);
    }

    /** Editing a confirmed booking sends it back to "Submitted" and frees its seats. */
    public function test_editing_a_confirmed_booking_returns_it_to_submitted_and_releases_seats(): void
    {
        $this->package->update(['date_mode' => 'fixed']);
        $departure = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-09-24',
            'seats_total' => 40, 'seats_booked' => 2, 'status' => 'open',
        ]);
        $booking = $this->booking();
        $booking->update([
            'status' => 'confirmed', 'confirmed_at' => now(),
            'package_date_id' => $departure->id, 'travel_date' => '2026-09-24',
        ]);

        $this->actingAs($this->agent)->get(route('agent.bookings.edit', $booking))->assertOk();
        $this->stage($booking, [
            'customer_phone' => '0198887766',
            'package_date_id' => $departure->id, 'travel_date' => '2026-09-24',
        ]);
        $this->resubmit($booking)->assertRedirect();

        $booking->refresh();
        $this->assertSame('pending_verification', $booking->status, 'Must land on "Submitted".');
        $this->assertSame('Submitted', $booking->agentStatusLabel());
        $this->assertNull($booking->confirmed_at);
        // The 2 seats it held as a confirmed booking are given back, so re-confirming
        // cannot count them twice.
        $this->assertSame(0, $departure->refresh()->seats_booked);
    }

    public function test_a_price_change_on_a_commissioned_booking_reverses_and_recalculates(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => 'confirmed', 'paid_amount' => 17000]);
        Commission::create([
            'booking_id' => $booking->id, 'earner_id' => $this->agent->id, 'level' => 1,
            'period' => now()->format('Y-m'), 'amount' => 1360, 'status' => 'approved',
        ]);

        // 2 Quad adults (17,000) → 3 Triple adults (28,302): the total moves upward, so
        // the guard about paying less than already paid does not trip.
        $this->stage($booking, [
            'rooms' => [['package_pricing_id' => $this->triple->id, 'adults' => 3, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'pax'   => [['key' => 'new-0', 'name' => 'Solo Traveller', 'type' => 'adult']],
        ]);
        $this->resubmit($booking)->assertRedirect();

        $this->assertSame(1, Commission::where('booking_id', $booking->id)->where('status', 'reversed')->count());
        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Commission reversed and recalculated after edit',
        ]);
    }

    public function test_the_review_screen_redirects_back_when_nothing_is_staged(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->get(route('agent.bookings.review.show', $booking))
            ->assertRedirect(route('agent.bookings.edit', $booking));
    }

    public function test_resubmitting_with_no_staged_draft_is_refused(): void
    {
        $booking = $this->booking();

        $this->expectException(ValidationException::class);
        app(BookingService::class)->resubmitRevision($booking, $this->agent);
    }

    public function test_another_agent_cannot_stage_or_resubmit(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($other)->post(route('agent.bookings.review', $booking), $this->payload($booking))->assertForbidden();
        $this->actingAs($other)->get(route('agent.bookings.review.show', $booking))->assertForbidden();
        $this->actingAs($other)->post(route('agent.bookings.resubmit', $booking), ['confirm' => '1'])->assertForbidden();
    }

    public function test_the_frozen_diff_matches_what_was_actually_applied(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766']);
        $this->resubmit($booking);

        $v2 = $booking->refresh()->versions()->first();
        $phone = collect($v2->changes)->firstWhere('key', 'customer.phone');

        $this->assertSame('0198887766', $phone['after']);
        $this->assertSame('0198887766', data_get($v2->payload, 'customer.phone'));
        $this->assertSame('0198887766', $booking->customer->phone);
    }

    /** The client's headline field: the diff must read "4 years → 6 years". */
    public function test_changing_a_childs_age_reads_as_years_in_the_diff_and_is_applied(): void
    {
        $booking = $this->booking();
        $child = $booking->pax()->create(['name' => 'Aliya', 'type' => 'child', 'age' => 4]);
        // Re-baseline so v1 carries the child at age 4.
        $booking->versions()->delete();
        app(BookingService::class)->requestRevision(
            tap($booking)->update(['status' => 'pending_verification']), $this->admin, 'Child age is wrong.', ['pax']
        );

        $this->stage($booking, ['pax' => [
            ['key' => (string) $booking->pax()->first()->id, 'name' => 'Nur Aina', 'type' => 'adult'],
            ['key' => (string) $child->id, 'name' => 'Aliya', 'type' => 'child', 'age' => 6],
        ]]);

        $rows = collect($this->actingAs($this->agent)
            ->get(route('agent.bookings.review.show', $booking))->viewData('rows'));
        $ageRow = $rows->first(fn ($r) => str_contains($r['label'], 'Child Age'));

        $this->assertNotNull($ageRow, 'A child age change must produce a diff row.');
        $this->assertSame('4 years', $ageRow['before']);
        $this->assertSame('6 years', $ageRow['after']);

        $this->resubmit($booking);
        $this->assertSame(6, $booking->refresh()->pax()->where('name', 'Aliya')->first()->age);
    }

    public function test_the_confirm_screen_and_success_screen_are_separate_steps(): void
    {
        $booking = $this->booking();
        $this->stage($booking, ['customer_phone' => '0198887766']);

        // Step 4 links to step 5 rather than submitting inline.
        $this->actingAs($this->agent)->get(route('agent.bookings.review.show', $booking))
            ->assertOk()->assertSee(route('agent.bookings.confirm', $booking), false);

        $this->actingAs($this->agent)->get(route('agent.bookings.confirm', $booking))
            ->assertOk()
            ->assertSee('Ready to resubmit?')
            ->assertSee('1 item(s) updated')
            ->assertSee('I confirm the information is correct.');

        $this->resubmit($booking);

        $this->actingAs($this->agent)->get(route('agent.bookings.submitted', [$booking, 'v' => 2]))
            ->assertOk()
            ->assertSee('Resubmission Successful!')
            ->assertSee('Back to My Customers');
    }

    public function test_the_confirm_screen_redirects_back_when_nothing_is_staged(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->get(route('agent.bookings.confirm', $booking))
            ->assertRedirect(route('agent.bookings.edit', $booking));
    }

    /** Regression: a departure fixes the travel date — apply() must not leave them disagreeing. */
    public function test_choosing_a_departure_sets_the_travel_date_even_when_the_form_clears_it(): void
    {
        $this->package->update(['date_mode' => 'fixed']);
        $departure = PackageDate::create([
            'package_id' => $this->package->id, 'depart_date' => '2026-09-24',
            'seats_total' => 40, 'seats_booked' => 0, 'status' => 'open',
        ]);
        $booking = $this->booking();

        // The edit form blanks travel_date for fixed-date packages, exactly as the browser does.
        $this->stage($booking, ['package_date_id' => $departure->id, 'travel_date' => null]);
        $this->resubmit($booking);

        $booking->refresh();
        $this->assertSame($departure->id, $booking->package_date_id);
        $this->assertSame(
            '2026-09-24',
            $booking->travel_date?->format('Y-m-d'),
            'travel_date must be derived from the chosen departure, never left null.'
        );
    }

    public function test_price_is_re_derived_and_never_trusted_from_the_stored_payload(): void
    {
        $booking = $this->booking();
        $revisions = app(BookingRevisionService::class);

        $payload = $revisions->snapshot($booking);
        $payload['money']['total_amount'] = '999999.00';   // a stale draft cannot dictate price

        $this->assertEqualsWithDelta(17000, $revisions->price($payload)['total'], 0.01);
    }
}
