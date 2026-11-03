<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes one in-app notification to the agent's installed devices. Queued so the
 * booking/commission request never waits on the push service's network round-trip.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $notificationId) {}

    public function handle(WebPushService $push): void
    {
        $notification = Notification::with('user')->find($this->notificationId);
        if (! $notification || ! $notification->user || ! $notification->user->hasRole('agent')) {
            return;
        }

        $push->sendToUser($notification->user, [
            'title' => $notification->icon.' '.$notification->title,
            'body' => $notification->body ?? '',
            'url' => $notification->url ?: route('agent.dashboard'),
            'tag' => 'notification-'.$notification->id,
        ]);
    }
}
