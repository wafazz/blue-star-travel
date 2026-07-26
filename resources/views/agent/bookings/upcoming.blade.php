@extends('layouts.agent')
@section('title', 'Upcoming')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">Upcoming</div><div class="sub">{{ $bookings->count() }} trip{{ $bookings->count() === 1 ? '' : 's' }} ahead</div></div>
  </div>

  <form method="GET" action="{{ route('agent.upcoming') }}" style="padding:12px 16px 0">
    <input type="hidden" name="by" value="{{ $by }}">
    <input type="search" name="q" value="{{ request('q') }}" class="inp" style="margin:0"
           placeholder="🔍 Search bookings">
  </form>

  {{-- Two-up switch: only the sort/grouping changes, the list is always upcoming trips. --}}
  <div class="tog">
    <a href="{{ route('agent.upcoming', ['by' => 'arrival', 'q' => request('q')]) }}"
       class="{{ $by === 'arrival' ? 'on' : '' }}">Arrival date</a>
    <a href="{{ route('agent.upcoming', ['by' => 'reservation', 'q' => request('q')]) }}"
       class="{{ $by === 'reservation' ? 'on' : '' }}">Reservation date</a>
  </div>

  <div class="wrap">
    @forelse ($groups as $heading => $rows)
      <div class="grp">{{ $heading }}</div>

      @foreach ($rows as $booking)
        @php
          $from = $booking->arrivalDate();
          $to = $booking->returnDate();
          $nights = $booking->nights();
        @endphp
        <a class="card trip" href="{{ route('agent.bookings.show', $booking) }}">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
            <div style="font-weight:800;font-size:14px">{{ $booking->customer?->name ?? '—' }}</div>
            <span class="badge b-{{ $booking->agentStatusBadge() }}">{{ $booking->agentStatusLabel() }}</span>
          </div>

          <div class="tl-row">📅
            <span>
              @if ($from && $to && ! $from->isSameDay($to))
                {{ $from->format('M j') }} – {{ $to->format('M j, Y') }}
              @elseif ($from)
                {{ $from->format('M j, Y') }}
              @else
                Date not set yet
              @endif
            </span>
          </div>
          <div class="tl-row">🕐 <span>{{ $nights }} night{{ $nights === 1 ? '' : 's' }}</span></div>
          <div class="tl-row">👤 <span>{{ $booking->paxSummary() }}</span></div>
          <div class="tl-sub">{{ $booking->package?->title ?? '—' }}</div>
        </a>
      @endforeach
    @empty
      <div class="empty">
        @if (request('q'))
          No upcoming trip matches “{{ request('q') }}”.
        @else
          No upcoming trips.<br>Tap ➕ New to create a booking.
        @endif
      </div>
    @endforelse
  </div>

  <style>
    .tog{display:flex;gap:0;margin:12px 16px 0;background:#eef1f8;border-radius:12px;padding:3px}
    .tog a{flex:1;text-align:center;text-decoration:none;font-size:12px;font-weight:800;padding:9px 6px;
      border-radius:10px;color:var(--muted)}
    .tog a.on{background:#fff;color:var(--ink);box-shadow:0 2px 6px rgba(13,27,62,.10)}
    .grp{font-size:11.5px;font-weight:700;color:var(--muted);padding:14px 2px 6px}
    .card.trip{display:block;text-decoration:none;color:inherit;padding:13px 14px;margin-bottom:9px}
    .tl-row{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--ink);margin-top:6px}
    .tl-sub{font-size:11.5px;color:var(--muted);margin-top:8px}
  </style>
@endsection
