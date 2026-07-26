@extends('layouts.agent')
@section('title', 'My Customers')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">My Customers</div><div class="sub">{{ $bookings->total() }} total</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <form method="GET" action="{{ route('agent.bookings.index') }}" style="padding:12px 16px 0">
    @if (request('tab'))<input type="hidden" name="tab" value="{{ request('tab') }}">@endif
    <input type="search" name="q" value="{{ request('q') }}" class="inp" style="margin:0"
           placeholder="🔍 Search name / phone / booking no">
  </form>

  <div class="seg">
    <a href="{{ route('agent.bookings.index', ['q' => request('q')]) }}" class="{{ ! request('tab') ? 'on' : '' }}">All</a>
    @foreach (\App\Models\Booking::AGENT_TABS as $k => $label)
      <a href="{{ route('agent.bookings.index', ['tab' => $k, 'q' => request('q')]) }}" class="{{ request('tab') === $k ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="wrap">
    @forelse ($bookings as $booking)
      @php $travel = $booking->travel_date ?? $booking->packageDate?->depart_date; @endphp
      <div class="card" style="padding:13px 14px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div style="min-width:0">
            <div style="font-weight:800;font-size:14px">{{ $booking->customer?->name ?? '—' }}</div>
            <div class="m" style="font-size:11.5px;margin-top:2px">
              {{ $booking->package?->title ?? '—' }} · {{ $booking->total_pax }} Pax
            </div>
            <div class="m" style="font-size:11.5px">
              Travel: {{ $travel?->format('d M Y') ?? '—' }}
            </div>
            <div class="m" style="font-size:11.5px">{{ $booking->booking_no }} · RM {{ number_format($booking->total_amount, 0) }}</div>
          </div>
          <span class="badge b-{{ $booking->agentStatusBadge() }}">{{ $booking->agentStatusLabel() }}</span>
        </div>

        <div style="display:flex;gap:8px;margin-top:11px">
          <a href="{{ route('agent.bookings.show', $booking) }}" class="btn"
             style="flex:1;background:#eef1f8;color:var(--ink);padding:9px;font-size:12.5px">View</a>
          @if ($booking->isEditableByAgent())
            <a href="{{ route('agent.bookings.edit', $booking) }}" class="btn"
               style="flex:1;padding:9px;font-size:12.5px">Edit</a>
          @endif
        </div>
      </div>
    @empty
      <div class="empty">
        @if (request('q'))
          No match for “{{ request('q') }}”.
        @else
          No customers here yet.<br>Tap ➕ New to create a booking.
        @endif
      </div>
    @endforelse
    <div style="padding:10px 0">{{ $bookings->links() }}</div>

    <div class="card">
      <h3>Status Guide</h3>
      @foreach (\App\Models\Booking::AGENT_STATUS_GUIDE as $label => [$badge, $meaning])
        <div class="sum">
          <span class="badge b-{{ $badge }}">{{ $label }}</span>
          <span class="m" style="font-size:11.5px">{{ $meaning }}</span>
        </div>
      @endforeach
    </div>
  </div>
@endsection
