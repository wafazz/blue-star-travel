<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Services\Assistant\StaffDraftService;
use Illuminate\Http\Request;

/**
 * Staff view of visitor conversations and reply drafting (TASK-AI-031, TASK-AI-040).
 * Conversations contain what visitors typed, so this is HQ and super-admin only.
 */
class AiConversationController extends Controller
{
    public function index()
    {
        $conversations = AssistantConversation::query()
            ->with('user:id,name')
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(25);

        return view('manage.ai-assistant.conversations', ['conversations' => $conversations]);
    }

    public function show(AssistantConversation $conversation)
    {
        $conversation->load('user:id,name');

        return view('manage.ai-assistant.conversation', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    public function draft(Request $request, AssistantConversation $conversation, StaffDraftService $drafts)
    {
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
        ]);

        // Only visitor messages can be drafted against, so staff cannot draft from their own drafts.
        $message = $conversation->messages()->where('role', 'user')->findOrFail($data['message_id']);

        $result = $drafts->draft($message->body, $request->user()?->id, $conversation);

        return redirect()
            ->route('manage.ai-assistant.conversation', $conversation)
            ->with('draft', $result['text'])
            ->with('draft_for', $message->id)
            ->with('draft_status', $result['status']);
    }
}
