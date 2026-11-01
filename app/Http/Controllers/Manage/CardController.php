<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Digital business card for back-office staff (admin, HQ, super admin). Same rule as agents:
 * only title and phone are editable; name, email and login stay with HQ.
 */
class CardController extends Controller
{
    public function show(Request $request)
    {
        return view('manage.card.show', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Login email: changed by HQ only. Rejected if submitted, never silently dropped.
            'email' => ['prohibited'],
            'title' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->update([
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
        ]);

        return back()->with('ok', 'Business card updated.');
    }
}
