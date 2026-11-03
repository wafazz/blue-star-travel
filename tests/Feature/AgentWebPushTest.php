<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class AgentWebPushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/device-a';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function configured(): void
    {
        // A real key pair: the library validates the public key's length, so placeholders fail.
        $keys = VAPID::createVapidKeys();
        config([
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
        ]);
    }

    private function subscriptionPayload(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256-key', 'auth' => 'auth-secret']];
    }

    public function test_agent_can_subscribe_a_device(): void
    {
        $this->actingAs($this->agent)
            ->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->agent->id,
            'endpoint' => self::ENDPOINT,
            'p256dh' => 'p256-key',
            'auth' => 'auth-secret',
        ]);
    }

    public function test_resubscribing_same_device_does_not_duplicate(): void
    {
        $this->actingAs($this->agent)->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())->assertOk();
        $this->actingAs($this->agent)->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())->assertOk();

        $this->assertSame(1, PushSubscription::count());
    }

    public function test_shared_device_is_reassigned_to_the_newly_signed_in_agent(): void
    {
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($this->agent)->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())->assertOk();
        $this->actingAs($other)->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($other->id, PushSubscription::first()->user_id);
    }

    public function test_non_https_endpoint_is_rejected(): void
    {
        $this->actingAs($this->agent)
            ->postJson(route('agent.push.subscribe'), $this->subscriptionPayload('http://insecure.example/device'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson(route('agent.push.subscribe'), $this->subscriptionPayload())
            ->assertUnauthorized();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_unsubscribe_only_removes_the_callers_own_device(): void
    {
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        PushSubscription::create(['user_id' => $other->id, 'endpoint' => self::ENDPOINT, 'p256dh' => 'k', 'auth' => 'a']);

        $this->actingAs($this->agent)
            ->postJson(route('agent.push.unsubscribe'), ['endpoint' => self::ENDPOINT])
            ->assertOk();

        $this->assertSame(1, PushSubscription::count(), 'another agent\'s device must not be removed');
    }

    public function test_agent_notification_is_queued_for_push_when_configured(): void
    {
        $this->configured();
        Queue::fake();

        app(NotificationService::class)->notify($this->agent, 'commission', 'Commission earned', 'RM 120.00');

        $this->assertDatabaseHas('notifications', ['user_id' => $this->agent->id, 'title' => 'Commission earned']);
        Queue::assertPushed(SendPushNotification::class);
    }

    public function test_customer_notification_is_never_pushed(): void
    {
        $this->configured();
        Queue::fake();
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        app(NotificationService::class)->notify($customer, 'booking', 'Booking confirmed');

        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_in_app_notification_still_saved_when_push_is_not_configured(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        Queue::fake();

        app(NotificationService::class)->notify($this->agent, 'booking', 'Booking confirmed');

        $this->assertDatabaseHas('notifications', ['user_id' => $this->agent->id, 'title' => 'Booking confirmed']);
        Queue::assertNothingPushed();
    }

    public function test_job_sends_to_every_device_and_prunes_expired_subscriptions(): void
    {
        $this->configured();
        Queue::fake(); // run the job by hand below, after the mock is in place
        PushSubscription::create(['user_id' => $this->agent->id, 'endpoint' => 'https://push.example/ok', 'p256dh' => 'k', 'auth' => 'a']);
        PushSubscription::create(['user_id' => $this->agent->id, 'endpoint' => 'https://push.example/gone', 'p256dh' => 'k', 'auth' => 'a']);

        $notification = app(NotificationService::class)->notify($this->agent, 'booking', 'Booking confirmed', null, '/agent/bookings/1');
        Queue::assertPushed(SendPushNotification::class);

        $client = $this->createMock(WebPush::class);
        $client->expects($this->exactly(2))->method('queueNotification');
        $client->method('flush')->willReturn((function () {
            yield new MessageSentReport(new Request('POST', 'https://push.example/ok'), new Response(201));
            yield new MessageSentReport(new Request('POST', 'https://push.example/gone'), new Response(410), false, 'Gone');
        })());
        $this->app->instance(WebPush::class, $client);

        (new SendPushNotification($notification->id))->handle(app(WebPushService::class));

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/ok']);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/gone']);
    }

    public function test_agent_pages_carry_the_vapid_key_and_subscribe_endpoint(): void
    {
        $this->configured();
        $key = config('services.webpush.public_key');

        $this->actingAs($this->agent)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('name="vapid-public-key" content="'.$key.'"', false)
            ->assertSee(json_encode(route('agent.push.subscribe')), false)
            ->assertSee('data-push-enable', false);
    }
}
