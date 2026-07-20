@extends('layouts.admin')
@section('title', $ticket->ticket_no)
@section('console', 'Management')
@section('heading', 'Ticket ' . $ticket->ticket_no)

@section('content')
  <div class="d-flex gap-2 align-items-center mb-3">
    <a href="{{ route('manage.tickets.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
    <span class="badge text-bg-{{ $ticket->statusBadge() }} fs-6">{{ ucfirst($ticket->status) }}</span>
    <span class="badge text-bg-{{ $ticket->priorityBadge() }}">{{ ucfirst($ticket->priority) }}</span>
    <span class="badge text-bg-light border">{{ \App\Models\Ticket::CATEGORIES[$ticket->category] ?? $ticket->category }}</span>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4 mb-3">
        <h5 class="fw-bold">{{ $ticket->subject }}</h5>
        <div class="small text-secondary mb-3">Opened by {{ $ticket->user?->name }} · {{ $ticket->created_at->format('d M Y, H:i') }}</div>

        @foreach ($ticket->replies as $r)
          <div class="d-flex {{ $r->is_staff ? 'justify-content-end' : '' }} mb-3">
            <div class="p-3 rounded-3 {{ $r->is_staff ? 'bg-primary text-white' : 'bg-light' }}" style="max-width:80%">
              <div class="small fw-semibold {{ $r->is_staff ? 'text-white-50' : 'text-secondary' }}">{{ $r->is_staff ? 'Support · ' . $r->user?->name : $r->user?->name }}</div>
              <div style="white-space:pre-wrap">{{ $r->message }}</div>
              <div class="small {{ $r->is_staff ? 'text-white-50' : 'text-secondary' }} mt-1" style="font-size:.72rem">{{ $r->created_at->format('d M Y, H:i') }}</div>
            </div>
          </div>
        @endforeach

        @if (! in_array($ticket->status, ['closed']))
          <form method="POST" action="{{ route('manage.tickets.reply', $ticket) }}" class="mt-3">
            @csrf
            <textarea name="message" rows="3" class="form-control mb-2" placeholder="Type your reply…" required></textarea>
            <button class="btn btn-brand">Send Reply</button>
          </form>
        @endif
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Manage</h6>
        <div class="small text-secondary mb-1">From</div>
        <div class="fw-semibold mb-3">{{ $ticket->user?->name }}<br><span class="small text-secondary">{{ $ticket->user?->email }}</span></div>
        <form method="POST" action="{{ route('manage.tickets.status', $ticket) }}">
          @csrf
          <label class="form-label small fw-semibold">Set status</label>
          <select name="status" class="form-select mb-2" onchange="this.form.submit()">
            @foreach (['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $k => $label)
              <option value="{{ $k }}" @selected($ticket->status === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </form>
      </div>
    </div>
  </div>
@endsection
