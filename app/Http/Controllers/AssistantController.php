<?php

namespace App\Http\Controllers;

use App\Services\Assistant\AssistantService;
use App\Services\Assistant\PromptSanitiser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint for the assistant widget. Works for visitors and logged-in customers (OQ-3).
 * Nothing here returns a provider error or key to the browser.
 */
class AssistantController extends Controller
{
    public function message(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:' . PromptSanitiser::MAX_USER_CHARS],
        ]);

        // Hash the session ID with the app key so the stored value cannot be used to hijack a session.
        $sessionHash = hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key'));

        $result = $assistant->answer($data['message'], $request->user()?->id, $sessionHash, (string) $request->ip());

        $status = match ($result['status']) {
            'limited' => 429,
            'unavailable', 'disabled' => 503,
            default => 200,
        };

        return response()->json($result, $status);
    }
}
