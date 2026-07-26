<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        // `admin` reaches /manage only through the granted-permission set (EnsurePermission).
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
            'code' => "PKG-{$n}", 'title' => "Package {$n}", 'slug' => "package-{$n}",
            'category' => 'domestic', 'status' => 'active', 'provider_id' => $provider->id,
        ]);
        $customer = Customer::create(['name' => "Customer {$n}", 'agent_id' => $this->agent->id]);

        return Booking::create([
            'booking_no'  => "BK-REV-{$n}",
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $this->agent->id,
            'provider_id' => $provider->id,
            'status'      => $status,
        ] + $extra);
    }

    private function requestRevision(Booking $booking, array $payload = [])
    {
        return $this->actingAs($this->admin)->post(route('manage.bookings.revision', $booking), $payload + [
            'remark' => 'Child age is missing.',
            'fields' => ['pax', 'payment.slip'],
        ]);
    }

    public function test_admin_sends_a_booking_back_with_a_remark_and_flagged_fields(): void
    {
        $booking = $this->booking();

        $this->requestRevision($booking)->assertRedirect();

        $booking->refresh();
        $this->assertSame('needs_revision', $booking->status);
        $this->assertNotNull($booking->revision_requested_at);

        $request = $booking->openRevisionRequest;
        $this->assertSame('Child age is missing.', $request->remark);
        $this->assertSame(['pax', 'payment.slip'], $request->fields);
        $this->assertSame($this->admin->id, $request->requested_by);
        $this->assertSame(['Passengers', 'Payment Receipt'], $request->fieldLabels());
    }

    public function test_the_remark_lands_on_the_timeline_and_notifies_the_agent_only(): void
    {
        $booking = $this->booking();

        $this->requestRevision($booking);

        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Revision requested from agent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agent->id,
            'title'   => "Booking {$booking->booking_no} needs revision",
        ]);
        // Q8 is still open — the customer must not be told before Fakrul decides.
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_booking_out_with_the_provider_is_recalled_first(): void
    {
        $booking = $this->booking('waiting_provider_confirmation', [
            'provider_status'     => 'pending',
            'sent_to_provider_at' => now(),
        ]);

        $this->requestRevision($booking);

        $booking->refresh();
        $this->assertSame('needs_revision', $booking->status);
        $this->assertSame('pending', $booking->provider_status);
        $this->assertNull($booking->sent_to_provider_at, 'The provider must not keep acting on stale data.');
        $this->assertDatabaseHas('booking_timeline', [
            'booking_id' => $booking->id,
            'action'     => 'Recalled from provider for revision',
        ]);
    }

    public function test_a_revision_is_refused_once_commission_exists(): void
    {
        $booking = $this->booking();
        Commission::create([
            'booking_id' => $booking->id,
            'earner_id'  => $this->agent->id,
            'level'      => 1,
            'period'     => now()->format('Y-m'),
            'amount'     => 500,
            'status'     => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->requestRevision($booking, $this->admin, 'fix it', ['pax']);
    }

    public function test_a_reversed_commission_does_not_block_a_revision(): void
    {
        $booking = $this->booking();
        Commission::create([
            'booking_id' => $booking->id,
            'earner_id'  => $this->agent->id,
            'level'      => 1,
            'period'     => now()->format('Y-m'),
            'amount'     => 500,
            'status'     => 'reversed',
        ]);

        $this->requestRevision($booking)->assertRedirect();
        $this->assertSame('needs_revision', $booking->refresh()->status);
    }

    public function test_a_paid_provider_approved_booking_cannot_be_revised(): void
    {
        $booking = $this->booking('waiting_provider_confirmation', [
            'provider_status' => 'approved',
            'paid_amount'     => 1500,
        ]);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->requestRevision($booking, $this->admin, 'fix it', ['pax']);
    }

    public function test_a_confirmed_booking_cannot_be_revised(): void
    {
        $booking = $this->booking('confirmed');

        $this->expectException(ValidationException::class);
        app(BookingService::class)->requestRevision($booking, $this->admin, 'fix it', ['pax']);
    }

    public function test_a_remark_and_at_least_one_field_are_required(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.revision', $booking), ['remark' => '', 'fields' => []])
            ->assertSessionHasErrors(['remark', 'fields']);

        $this->assertSame('pending_verification', $booking->refresh()->status);
    }

    public function test_unflaggable_field_keys_are_rejected(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)
            ->post(route('manage.bookings.revision', $booking), [
                'remark' => 'change the agent', 'fields' => ['booking.agent_id'],
            ])
            ->assertSessionHasErrors('fields.0');
    }

    public function test_an_agent_cannot_request_a_revision(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)
            ->post(route('manage.bookings.revision', $booking), ['remark' => 'x', 'fields' => ['pax']])
            ->assertForbidden();
    }

    public function test_a_second_round_supersedes_the_first_open_request(): void
    {
        $booking = $this->booking();
        $this->requestRevision($booking, ['remark' => 'round one']);

        // refresh() first — the service moved the row, so update() on the stale instance
        // would see no change and skip the write.
        $booking->refresh()->update(['status' => 'pending_verification']);
        $this->requestRevision($booking, ['remark' => 'round two']);

        $this->assertSame(2, $booking->revisionRequests()->count());
        $this->assertSame(1, $booking->revisionRequests()->where('status', 'open')->count());
        $this->assertSame('round two', $booking->refresh()->openRevisionRequest->remark);
    }

    public function test_staff_cannot_confirm_a_booking_that_is_out_with_the_agent(): void
    {
        $booking = $this->booking();
        $this->requestRevision($booking);
        $booking->update(['status' => 'pending_verification']);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->confirm($booking, $this->admin);
    }

    public function test_the_agent_sees_the_remark_and_the_flagged_field_labels(): void
    {
        $booking = $this->booking();
        $this->requestRevision($booking);

        $response = $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Child age is missing.');
        $response->assertSee('Passengers');
        $response->assertSee('Payment Receipt');
        $response->assertSee('Need Revision');
    }
}
