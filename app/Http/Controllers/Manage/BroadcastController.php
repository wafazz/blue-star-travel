<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function create()
    {
        return view('manage.broadcast.create');
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'audience' => ['required', 'in:agent,customer,provider'],
            'title'    => ['required', 'string', 'max:120'],
            'body'     => ['nullable', 'string', 'max:1000'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['in:inapp,email,sms,whatsapp'],
        ]);

        $channels = $data['channels'] ?? ['inapp'];
        if (! in_array('inapp', $channels)) {
            $channels[] = 'inapp';
        }

        $count = $this->notifications->broadcastToRole($data['audience'], $data['title'], $data['body'] ?? null, null, $channels);

        return back()->with('ok', "Broadcast sent to {$count} {$data['audience']}(s).");
    }
}
