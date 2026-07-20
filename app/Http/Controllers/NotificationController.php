<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $items = $request->user()->notifications()->paginate(20);
        $view = $request->user()->hasRole('agent') ? 'agent.notifications' : 'manage.notifications';

        return view($view, compact('items'));
    }

    public function read(Notification $notification, Request $request)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->notifications->markRead($notification);

        // Only ever redirect inside the app — never bounce a user to an external URL.
        $url = (string) $notification->url;
        $internal = $url !== '' && str_starts_with($url, (string) config('app.url'));

        return $internal ? redirect($url) : back();
    }

    public function readAll(Request $request)
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('ok', 'All notifications marked read.');
    }
}
