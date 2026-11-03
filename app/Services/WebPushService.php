<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Real browser push (VAPID / RFC 8030) to installed agent PWAs.
 *
 * Delivery is best-effort: a push failure must never break the business action
 * that triggered it, so every error is logged and swallowed here.
 */
class WebPushService
{
    public function __construct(private WebPush $client) {}

    public static function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function subscribe(User $user, string $endpoint, string $p256dh, string $auth, ?string $userAgent): PushSubscription
    {
        return PushSubscription::updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => $user->id,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                'last_used_at' => now(),
            ],
        );
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }

    /** Sends one payload to every device the user has subscribed. Returns how many were accepted. */
    public function sendToUser(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty() || ! self::configured()) {
            return 0;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $this->client->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                    'contentEncoding' => 'aes128gcm',
                ]),
                $json,
                ['TTL' => 86400, 'urgency' => 'normal'],
            );
        }

        $accepted = 0;
        foreach ($this->client->flush() as $report) {
            if ($report->isSuccess()) {
                $accepted++;
                PushSubscription::where('endpoint', $report->getEndpoint())->update(['last_used_at' => now()]);

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                // 404/410 — the device uninstalled the app or revoked permission. Stop trying.
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();

                continue;
            }

            Log::warning('[webpush] delivery failed', [
                'user_id' => $user->id,
                'reason' => $report->getReason(),
            ]);
        }

        return $accepted;
    }
}
