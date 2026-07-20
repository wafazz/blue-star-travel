@extends('layouts.customer')
@section('title', $booking->booking_no)

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('customer.bookings.index') }}">‹</a>
    <div><div class="t">{{ $booking->booking_no }}</div><div class="sub">{{ $booking->package?->title }}</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">⚠️ {{ $errors->first() }}</div>@endif

  <div class="wrap">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span class="badge b-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
        <span style="font-size:12px;color:var(--muted)">{{ optional($booking->travel_date ?? $booking->packageDate?->depart_date)->format('d M Y') ?? 'Date TBC' }}</span>
      </div>
      <div class="sum"><span style="color:var(--muted)">Destination</span><span style="font-weight:700">{{ $booking->package?->destination ?? '—' }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Travellers</span><span style="font-weight:700">{{ $booking->adults }}A · {{ $booking->children }}C · {{ $booking->infants }}I</span></div>
      <div class="sum"><span style="color:var(--muted)">Booked on</span><span style="font-weight:700">{{ $booking->created_at->format('d M Y') }}</span></div>
    </div>

    <div class="card">
      <h3>Payment</h3>
      <div class="sum"><span style="color:var(--muted)">Subtotal</span><span style="font-weight:700">RM {{ number_format($booking->subtotal, 2) }}</span></div>
      @if ($booking->discount > 0)
        <div class="sum"><span style="color:var(--muted)">Discount</span><span style="font-weight:700;color:var(--ok)">− RM {{ number_format($booking->discount, 2) }}</span></div>
      @endif
      <div class="sum"><span style="color:var(--muted)">Total</span><span style="font-weight:800">RM {{ number_format($booking->total_amount, 2) }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Paid</span><span style="font-weight:700;color:var(--ok)">RM {{ number_format($booking->paid_amount, 2) }}</span></div>
      <div class="sum total" style="font-size:15px"><span>Balance</span><span style="color:{{ $booking->balance() > 0 ? 'var(--danger)' : 'var(--ok)' }}">RM {{ number_format($booking->balance(), 2) }}</span></div>

      @if ($booking->balance() > 0 && ! in_array($booking->status, ['cancelled', 'rejected']))
        <form method="POST" action="{{ route('gateway.initiate', $booking) }}" style="margin-top:12px">
          @csrf
          <input type="hidden" name="amount" value="{{ number_format($booking->balance(), 2, '.', '') }}">
          <button class="btn ok">⚡ Pay via FPX</button>
        </form>
        <div style="text-align:center;color:var(--muted);font-size:11px;margin:12px 0 6px">— or upload a bank transfer slip —</div>
        <form method="POST" action="{{ route('customer.bookings.payment', $booking) }}" enctype="multipart/form-data">
          @csrf
          <label class="lbl">Amount (RM)</label>
          <input type="number" name="amount" step="0.01" min="0.01" value="{{ number_format($booking->balance(), 2, '.', '') }}" class="inp" required>
          <label class="lbl">Method</label>
          <select name="method" class="inp">@foreach (\App\Models\Payment::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
          <label class="lbl">Reference (optional)</label>
          <input type="text" name="reference" class="inp">
          <label class="lbl">Payment slip</label>
          <input type="file" name="slip" accept="image/*" class="inp" required>
          <button class="btn">Upload Payment Slip</button>
        </form>
      @endif
    </div>

    @if ($booking->payments->isNotEmpty())
      <div class="card">
        <h3>Payment History</h3>
        @foreach ($booking->payments as $pay)
          <div class="sum">
            <span style="color:var(--muted)">{{ $pay->created_at->format('d M Y') }} · {{ $pay->methodLabel() }}</span>
            <span style="font-weight:700">RM {{ number_format($pay->amount, 2) }}
              <span class="badge b-{{ $pay->status === 'verified' ? 'success' : ($pay->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($pay->status) }}</span>
            </span>
          </div>
        @endforeach
      </div>
    @endif

    @if ($booking->documents->isNotEmpty())
      <div class="card">
        <h3>My Documents</h3>
        @foreach ($booking->documents as $doc)
          <a class="brow" href="{{ route('documents.download.portal', $doc) }}"><div class="n">📄 {{ $doc->typeLabel() }}</div><span class="badge b-info">PDF</span></a>
        @endforeach
      </div>
    @endif

    <div class="card">
      <h3>Trip Progress</h3>
      <ul class="tl">
        @foreach ($booking->timeline as $t)
          <li><div class="dot"></div><div><div class="a">{{ $t->action }}</div>@if ($t->note)<div class="nt">{{ $t->note }}</div>@endif<div class="tm">{{ $t->created_at->format('d M Y, H:i') }}</div></div></li>
        @endforeach
      </ul>
    </div>

    <div style="padding-bottom:20px">
      <a class="btn ghost" href="{{ route('customer.tickets.create') }}">🎧 Need help with this booking?</a>
    </div>
  </div>
@endsection
