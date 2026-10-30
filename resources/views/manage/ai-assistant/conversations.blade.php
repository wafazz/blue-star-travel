@extends('layouts.admin')
@section('title', 'AI Conversations')
@section('console', 'Management')
@section('heading', 'AI Conversations')

@section('content')
  <div class="card p-3 p-lg-4">
    <p class="text-secondary small mb-3">Visitor questions to the assistant, newest first. Visitor text is shown here only to HQ staff.</p>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Last activity</th>
            <th>Visitor</th>
            <th class="text-end">Messages</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($conversations as $conversation)
            <tr>
              <td class="small">{{ $conversation->updated_at?->format('d M Y, H:i') }}</td>
              <td class="small">{{ $conversation->user?->name ?? 'Anonymous visitor' }}</td>
              <td class="text-end">{{ $conversation->messages_count }}</td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('manage.ai-assistant.conversation', $conversation) }}">Open</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-secondary small">No conversations yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $conversations->links() }}</div>
  </div>
@endsection
