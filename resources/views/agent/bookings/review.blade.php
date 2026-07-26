@extends('layouts.agent')
@section('title', 'Review Changes')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.edit', $booking) }}">‹</a>
    <div><div class="t">Review Changes</div><div class="sub">{{ $booking->booking_no }}</div></div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <div class="wrap">
    <div class="card">
      <div style="font-size:13px;line-height:1.5">Please review the changes before resubmitting.</div>
      @if ($draft->isStale())
        <div class="m" style="font-size:11.5px;color:var(--danger);margin-top:8px">
          ⚠️ This booking changed while you were editing. The comparison below is against the booking as it stands now.
        </div>
      @endif
    </div>

    @forelse (collect($rows)->groupBy('group') as $group => $groupRows)
      <div class="card">
        <h3>{{ $group }}</h3>
        @foreach ($groupRows as $row)
          <div style="padding:9px 0;border-bottom:1px solid var(--line)">
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">
              {{ $row['label'] }}
              @if (($row['change'] ?? '') === 'added')<span style="color:#0e9455">· added</span>@endif
              @if (($row['change'] ?? '') === 'removed')<span style="color:#d13b3b">· removed</span>@endif
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <span style="font-size:12.5px;color:#d13b3b;text-decoration:line-through">{{ $row['before'] ?? '—' }}</span>
              <span style="color:var(--muted)">→</span>
              <span style="font-size:13px;font-weight:800;color:#0e9455">{{ $row['after'] ?? '—' }}</span>
            </div>
          </div>
        @endforeach
      </div>
    @empty
      <div class="card"><div class="empty">Nothing has changed yet.<br>Go back and edit the booking first.</div></div>
    @endforelse

    <div class="card">
      <h3>Totals</h3>
      <div class="sum"><span style="color:var(--muted)">Current total</span><span style="font-weight:700">RM {{ number_format($booking->total_amount, 2) }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Already paid</span><span style="font-weight:700">RM {{ number_format($booking->paid_amount, 2) }}</span></div>
      <div class="sum total"><span>New total</span><span>RM {{ number_format($newTotal, 2) }}</span></div>
      @if ($newTotal < (float) $booking->paid_amount)
        <div class="m" style="font-size:11.5px;color:var(--danger);margin-top:6px">
          This is below what has already been paid — a refund has to be raised before this can be resubmitted.
        </div>
      @endif
      <div class="m" style="font-size:11.5px;margin-top:6px">Pricing is re-checked against today's rates when you submit.</div>
    </div>

    <div style="display:flex;gap:9px;margin-bottom:20px">
      <a href="{{ route('agent.bookings.edit', $booking) }}" class="btn"
         style="flex:1;background:#eef1f8;color:var(--ink)">Back to Edit</a>
      @if (count($rows))
        <a href="{{ route('agent.bookings.confirm', $booking) }}" class="btn" style="flex:1">Submit Again ›</a>
      @endif
    </div>
  </div>
@endsection
