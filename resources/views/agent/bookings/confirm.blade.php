@extends('layouts.agent')
@section('title', 'Submit Again')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.review.show', $booking) }}">‹</a>
    <div><div class="t">Submit Again</div><div class="sub">{{ $booking->booking_no }}</div></div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <div class="wrap">
    <div class="card" style="text-align:center;padding:26px 18px">
      <div style="font-size:46px;line-height:1">📨</div>
      <h3 style="margin:14px 0 6px;font-size:17px">Ready to resubmit?</h3>
      <div class="m" style="font-size:12.5px;line-height:1.6">
        The updated information will be sent to the operation team for review.
      </div>
    </div>

    <form method="POST" action="{{ route('agent.bookings.resubmit', $booking) }}">
      @csrf
      <div class="card">
        <div class="sum"><span style="color:var(--muted)">Customer</span><span style="font-weight:700">{{ $booking->customer?->name }}</span></div>
        <div class="sum"><span style="color:var(--muted)">Booking ID</span><span style="font-weight:700">{{ $booking->booking_no }}</span></div>
        <div class="sum"><span style="color:var(--muted)">Changes</span><span style="font-weight:700">{{ $changes }} item(s) updated</span></div>

        @if ($forfeit['packs'] > 0)
          <div class="sum" style="border-top:1px solid var(--line);padding-top:10px">
            <span style="color:var(--danger)">Deposit forfeited</span>
            <span style="font-weight:800;color:var(--danger)">RM {{ number_format($forfeit['amount'], 2) }}</span>
          </div>
          <div class="m" style="font-size:11.5px;line-height:1.6;color:var(--danger)">
            {{ $forfeit['packs'] }} cancelled pack(s) × RM {{ number_format($forfeit['rate'], 2) }}, deducted from the
            RM {{ number_format($booking->paid_amount, 2) }} already paid. This cannot be reversed once submitted.
          </div>
        @endif

        <label style="display:flex;gap:9px;align-items:flex-start;font-size:12.5px;padding:12px 0 4px">
          <input type="checkbox" name="confirm" value="1" style="margin-top:2px">
          <span>
            I confirm the information is correct.
            @if ($forfeit['packs'] > 0)
              I have told the customer that RM {{ number_format($forfeit['amount'], 2) }} of what they paid is forfeited.
            @endif
          </span>
        </label>
      </div>

      <button class="btn" @disabled($changes === 0) style="margin-bottom:10px">Yes, Submit Now</button>
    </form>

    <a href="{{ route('agent.bookings.review.show', $booking) }}" class="btn"
       style="background:transparent;color:var(--muted);box-shadow:none;margin-bottom:20px">Cancel</a>
  </div>
@endsection
