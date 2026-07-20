<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Multi-channel notifications. In-app is always persisted; email / SMS / WhatsApp
 * are logged stubs until real vendors are wired (Planning §10 open question).
 */
class NotificationService
{
    const ICONS = [
        'booking'      => '📋',
        'commission'   => '💰',
        'withdrawal'   => '🏧',
        'redemption'   => '🎁',
        'ticket'       => '🎧',
        'broadcast'    => '📢',
        'general'      => '🔔',
    ];

    public function notify(User $user, string $type, string $title, ?string $body = null, ?string $url = null, array $channels = ['inapp']): Notification
    {
        $notification = Notification::create([
            'user_id'  => $user->id,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'url'      => $url,
            'icon'     => self::ICONS[$type] ?? '🔔',
            'channels' => $channels,
        ]);

        foreach (array_diff($channels, ['inapp']) as $channel) {
            $this->dispatchExternal($channel, $user, $title, $body);
        }

        return $notification;
    }

    /** @param iterable<User> $users */
    public function notifyMany(iterable $users, string $type, string $title, ?string $body = null, ?string $url = null, array $channels = ['inapp']): int
    {
        $count = 0;
        foreach ($users as $user) {
            $this->notify($user, $type, $title, $body, $url, $channels);
            $count++;
        }

        return $count;
    }

    public function broadcastToRole(string $role, string $title, ?string $body, ?string $url = null, array $channels = ['inapp']): int
    {
        return $this->notifyMany(User::where('role', $role)->get(), 'broadcast', $title, $body, $url, $channels);
    }

    public function markRead(Notification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): void
    {
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function dispatchExternal(string $channel, User $user, string $title, ?string $body): void
    {
        // Vendor integration point — logged for now.
        Log::info("[notify:{$channel}] to {$user->email}: {$title}" . ($body ? " — {$body}" : ''));
    }
}
