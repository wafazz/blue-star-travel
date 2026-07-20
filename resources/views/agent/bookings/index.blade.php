@extends('layouts.agent')
@section('title', 'My Bookings')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">My Bookings</div><div class="sub">{{ $bookings->total() }} total</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="seg">
    <a href="{{ route('agent.bookings.index') }}" class="{{ ! request('status') ? 'on' : '' }}">All</a>
    @foreach (['pending_verification' => 'Pending', 'waiting_provider_confirmation' => 'Provider', 'confirmed' => 'Confirmed', 'completed' => 'Completed'] as $k => $label)
      <a href="{{ route('agent.bookings.index', ['status' => $k]) }}" class="{{ request('status') === $k ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="wrap">
    @forelse ($bookings as $booking)
      <a class="brow" href="{{ route('agent.bookings.show', $booking) }}">
        <div>
          <div class="n">{{ $booking->package?->title ?? '—' }}</div>
          <div class="m">{{ $booking->booking_no }} · {{ $booking->customer?->name }} · RM {{ number_format($booking->total_amount, 0) }}</div>
        </div>
        <span class="badge b-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
      </a>
    @empty
      <div class="empty">No bookings yet.<br>Tap ➕ New to create one.</div>
    @endforelse
    <div style="padding:10px 0">{{ $bookings->links() }}</div>
  </div>
@endsection
