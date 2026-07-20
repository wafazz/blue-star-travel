@extends('layouts.customer')
@section('title', 'My Trips')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('customer.dashboard') }}">‹</a>
    <div><div class="t">My Trips</div><div class="sub">{{ $bookings->total() }} total</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="seg">
    <a href="{{ route('customer.bookings.index') }}" class="{{ ! request('status') ? 'on' : '' }}">All</a>
    @foreach (['pending_verification' => 'Pending', 'waiting_provider_confirmation' => 'Processing', 'confirmed' => 'Confirmed', 'completed' => 'Completed'] as $k => $label)
      <a href="{{ route('customer.bookings.index', ['status' => $k]) }}" class="{{ request('status') === $k ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="wrap">
    @forelse ($bookings as $booking)
      <a class="brow" href="{{ route('customer.bookings.show', $booking) }}">
        <div>
          <div class="n">{{ $booking->package?->title ?? '—' }}</div>
          <div class="m">{{ $booking->booking_no }} · {{ $booking->total_pax }} pax · RM {{ number_format($booking->total_amount, 0) }}</div>
        </div>
        <span class="badge b-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
      </a>
    @empty
      <div class="empty">No trips yet.<br>Tap 🗺️ Packages to find your next journey.</div>
    @endforelse
    <div style="padding:10px 0">{{ $bookings->links() }}</div>
  </div>
@endsection
