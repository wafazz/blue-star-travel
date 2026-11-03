<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends a one-off "push is working" message to one agent's enabled devices (HQ test button). */
class SendTestPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $userId) {}

    public function handle(WebPushService $push): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->hasRole('agent')) {
            return;
        }

        $push->sendToUser($user, [
            'title' => '🔔 Test notification',
            'body'  => 'Push alerts are working on this device.',
            'url'   => route('agent.dashboard'),
            'tag'   => 'push-test',
        ]);
    }
}
