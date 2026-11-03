<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(private WebPushService $push) {}

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:64'],
        ]);

        $this->push->subscribe(
            $request->user(),
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $request->userAgent(),
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $this->push->unsubscribe($request->user(), $data['endpoint']);

        return response()->json(['ok' => true]);
    }
}
