@extends('layouts.agent')
@section('title', 'Resubmission Successful')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.index') }}">‹</a>
    <div><div class="t">Resubmission Successful!</div><div class="sub">{{ $booking->booking_no }}</div></div>
  </div>

  <div class="wrap">
    <div class="card" style="text-align:center;padding:30px 18px">
      <div style="font-size:54px;line-height:1">✅</div>
      <h3 style="margin:14px 0 6px;font-size:17px">Resubmission Successful!</h3>
      <div class="m" style="font-size:12.5px;line-height:1.6">
        The customer information has been sent to the operation team for review.
      </div>
    </div>

    <div class="card">
      <div class="sum"><span style="color:var(--muted)">Customer</span><span style="font-weight:700">{{ $booking->customer?->name }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Booking ID</span><span style="font-weight:700">{{ $booking->booking_no }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Submitted</span><span style="font-weight:700">{{ optional($booking->resubmitted_at)->format('d M Y, H:i') }}</span></div>
      @if ($version)
        <div class="sum"><span style="color:var(--muted)">Version</span><span style="font-weight:700">v{{ $version }}</span></div>
      @endif
    </div>

    <a href="{{ route('agent.bookings.index') }}" class="btn" style="margin-bottom:10px">Back to My Customers</a>
    <a href="{{ route('agent.bookings.show', $booking) }}" class="btn"
       style="background:#eef1f8;color:var(--ink);margin-bottom:20px">View this booking</a>
  </div>
@endsection
