@extends('layouts.admin')
@section('title', 'AI Conversation')
@section('console', 'Management')
@section('heading', 'AI Conversation #' . $conversation->id)

@section('content')
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="small text-secondary">{{ $conversation->user?->name ?? 'Anonymous visitor' }} · started {{ $conversation->created_at?->format('d M Y, H:i') }}</div>
          <a class="small" href="{{ route('manage.ai-assistant.conversations') }}">← All conversations</a>
        </div>

        @foreach ($messages as $message)
          @php
            $isVisitor = $message->role === 'user';
            $label = $isVisitor ? 'Visitor' : ($message->route === 'draft' ? 'Staff draft' : 'Assistant');
          @endphp
          <div class="mb-3 {{ $isVisitor ? 'text-end' : '' }}">
            <div class="small text-secondary mb-1">
              {{ $label }} · {{ $message->created_at?->format('d M, H:i') }}
              @if ($message->route === 'handoff') · <span class="badge text-bg-warning">handed to staff</span> @endif
              @if (! in_array($message->status, ['ok'], true)) · <span class="badge text-bg-secondary">{{ $message->status }}</span> @endif
            </div>
            <div class="d-inline-block text-start px-3 py-2 rounded-3 {{ $isVisitor ? 'bg-primary text-white' : 'bg-body-secondary' }}" style="max-width:85%;white-space:pre-wrap">{{ $message->body }}</div>

            @if ($isVisitor && $message->route !== 'handoff')
              <form method="POST" action="{{ route('manage.ai-assistant.draft', $conversation) }}" class="mt-1">
                @csrf
                <input type="hidden" name="message_id" value="{{ $message->id }}">
                <button class="btn btn-sm btn-outline-primary">Draft a reply</button>
              </form>
            @endif
          </div>
        @endforeach
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-1">Reply draft</h6>
        <p class="text-secondary small">A draft is written for you to review. Nothing is sent to the visitor automatically. Contact details and ID numbers are removed before the AI sees the message.</p>

        @if (session('draft_status') && session('draft_status') !== 'ok')
          <div class="alert alert-warning small mb-2">{{ session('draft') }}</div>
        @elseif (session('draft'))
          <label class="form-label small fw-semibold">Draft for message #{{ session('draft_for') }}</label>
          <textarea class="form-control" rows="8" readonly onclick="this.select()">{{ session('draft') }}</textarea>
          <div class="form-text">Copy, edit, and send it yourself.</div>
        @else
          <p class="small text-secondary mb-0">Choose "Draft a reply" under a visitor message.</p>
        @endif
      </div>
    </div>
  </div>
@endsection
