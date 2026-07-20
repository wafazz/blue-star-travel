@extends('layouts.customer')
@section('title', 'Support')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('customer.dashboard') }}">‹</a>
    <div><div class="t">🎧 Support</div><div class="sub">{{ $tickets->total() }} ticket(s)</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="wrap">
    <a href="{{ route('customer.tickets.create') }}" class="btn" style="margin-bottom:14px">＋ New Support Ticket</a>
    @forelse ($tickets as $t)
      <a class="brow" href="{{ route('customer.tickets.show', $t) }}">
        <div><div class="n">{{ $t->subject }}</div><div class="m">{{ $t->ticket_no }} · {{ \App\Models\Ticket::CATEGORIES[$t->category] ?? $t->category }} · {{ optional($t->last_reply_at)->diffForHumans() }}</div></div>
        <span class="badge b-{{ $t->statusBadge() }}">{{ ucfirst($t->status) }}</span>
      </a>
    @empty
      <div class="empty">No tickets yet.<br>Open one if you need help.</div>
    @endforelse
    <div style="padding:10px 0">{{ $tickets->links() }}</div>
  </div>
@endsection
