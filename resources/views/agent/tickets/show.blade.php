@extends('layouts.agent')
@section('title', $ticket->ticket_no)

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.tickets.index') }}">‹</a>
    <div><div class="t">{{ $ticket->ticket_no }}</div><div class="sub">{{ Str::limit($ticket->subject, 34) }}</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="wrap">
    <div class="card" style="display:flex;justify-content:space-between;align-items:center">
      <div><div class="n" style="font-weight:800">{{ $ticket->subject }}</div><div class="m">{{ \App\Models\Ticket::CATEGORIES[$ticket->category] ?? $ticket->category }} · {{ ucfirst($ticket->priority) }}</div></div>
      <span class="badge b-{{ $ticket->statusBadge() }}">{{ ucfirst($ticket->status) }}</span>
    </div>

    <div class="card">
      @foreach ($ticket->replies as $r)
        <div style="margin-bottom:12px;{{ $r->is_staff ? 'text-align:right' : '' }}">
          <div style="display:inline-block;max-width:85%;padding:10px 13px;border-radius:14px;text-align:left;background:{{ $r->is_staff ? 'linear-gradient(135deg,#1466ff,#0b3fd1)' : '#eef2fb' }};color:{{ $r->is_staff ? '#fff' : 'var(--ink)' }}">
            <div style="font-size:10.5px;font-weight:700;opacity:.8">{{ $r->is_staff ? 'Support' : 'You' }}</div>
            <div style="font-size:13.5px;white-space:pre-wrap">{{ $r->message }}</div>
            <div style="font-size:10px;opacity:.7;margin-top:3px">{{ $r->created_at->format('d M, H:i') }}</div>
          </div>
        </div>
      @endforeach

      @if ($ticket->status !== 'closed')
        <form method="POST" action="{{ route('agent.tickets.reply', $ticket) }}" style="margin-top:10px">
          @csrf
          <textarea name="message" rows="3" class="inp" placeholder="Type a reply…" required></textarea>
          <button class="btn">Send Reply</button>
        </form>
      @else
        <div class="empty" style="padding:16px">This ticket is closed.</div>
      @endif
    </div>
  </div>
@endsection
