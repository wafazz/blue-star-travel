<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function show(Request $request)
    {
        return view('agent.card.show', ['user' => $request->user()]);
    }

    // Only what the card shows is editable. Name, email and member ID belong to the account
    // and are changed by HQ — an agent must not be able to rename themselves on a shared card.
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
