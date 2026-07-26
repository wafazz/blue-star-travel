@extends('layouts.agent')
@section('title', $booking->booking_no)

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.index') }}">‹</a>
    <div><div class="t">{{ $booking->booking_no }}</div><div class="sub">{{ $booking->package?->title }}</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <div class="wrap">
    @if ($booking->needsRevision() && $booking->openRevisionRequest)
      <div class="card" style="border-left:4px solid #b26a00;background:#fffaf2">
        <h3 style="color:#b26a00;margin-bottom:6px">📝 Admin asked you to fix this</h3>
        <div style="font-size:13px;line-height:1.5">{{ $booking->openRevisionRequest->remark }}</div>
        <div style="margin-top:10px">
          @foreach ($booking->openRevisionRequest->fieldLabels() as $label)
            <span class="badge b-warning" style="margin:0 4px 4px 0;display:inline-block">{{ $label }}</span>
          @endforeach
        </div>
        <div style="font-size:11.5px;color:var(--muted);margin-top:8px">
          Requested {{ $booking->openRevisionRequest->created_at->diffForHumans() }}
        </div>
        <a href="{{ route('agent.bookings.edit', $booking) }}" class="btn" style="margin-top:12px">
          {{ $booking->draft ? '✏️ Continue editing' : '✏️ Edit & Resubmit' }}
        </a>
      </div>
    @elseif ($booking->isEditableByAgent())
      <a href="{{ route('agent.bookings.edit', $booking) }}" class="btn" style="margin-bottom:12px">✏️ Edit booking</a>
    @endif

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span class="badge b-{{ $booking->agentStatusBadge() }}">{{ $booking->agentStatusLabel() }}</span>
        <span class="m" style="font-size:12px;color:var(--muted)">{{ optional($booking->travel_date ?? $booking->packageDate?->depart_date)->format('d M Y') ?? '—' }}</span>
      </div>
      <div class="sum"><span style="color:var(--muted)">Customer</span><span style="font-weight:700">{{ $booking->customer?->name }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Pax</span><span style="font-weight:700">{{ $booking->adults }}A · {{ $booking->children }}C{{ $booking->seniors ? ' · ' . $booking->seniors . 'S' : '' }} · {{ $booking->infants }}I</span></div>
      @foreach ($booking->rooms as $room)
        <div class="sum">
          <span style="color:var(--muted)">{{ $room->room_name }} × {{ $room->rooms }} rm</span>
          <span style="font-weight:700">RM {{ number_format($room->subtotal, 2) }}</span>
        </div>
      @endforeach
      <div class="sum"><span style="color:var(--muted)">Provider</span><span style="font-weight:700">{{ $booking->provider?->name ?? '—' }}</span></div>
      @if ($booking->pickup_location)
        <div class="sum"><span style="color:var(--muted)">Pickup</span><span style="font-weight:700">{{ $booking->pickup_location }}</span></div>
      @endif
      @if ($booking->arrival_time)
        <div class="sum"><span style="color:var(--muted)">Arrival</span><span style="font-weight:700">{{ substr($booking->arrival_time, 0, 5) }}</span></div>
      @endif
    </div>

    <div class="card">
      <h3>Payment</h3>
      <div class="sum"><span style="color:var(--muted)">Total</span><span style="font-weight:800">RM {{ number_format($booking->total_amount, 2) }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Paid</span><span style="font-weight:700;color:var(--ok)">RM {{ number_format($booking->paid_amount, 2) }}</span></div>
      <div class="sum total" style="font-size:15px"><span>Balance</span><span style="color:{{ $booking->balance() > 0 ? 'var(--danger)' : 'var(--ok)' }}">RM {{ number_format($booking->balance(), 2) }}</span></div>

      @if ($booking->balance() > 0 && ! in_array($booking->status, ['cancelled', 'rejected']))
        <form method="POST" action="{{ route('gateway.initiate', $booking) }}" style="margin-top:12px">
          @csrf
          <input type="hidden" name="amount" value="{{ number_format($booking->balance(), 2, '.', '') }}">
          <button class="btn" style="background:linear-gradient(135deg,#16b364,#0e9455)">⚡ Pay Balance via FPX</button>
        </form>
        <div style="text-align:center;color:var(--muted);font-size:11px;margin:12px 0 6px">— or upload a bank transfer slip —</div>
        <form method="POST" action="{{ route('agent.bookings.payment', $booking) }}" enctype="multipart/form-data">
          @csrf
          <label class="lbl">Amount (RM)</label>
          <input type="number" name="amount" step="0.01" min="0.01" value="{{ number_format($booking->balance(), 2, '.', '') }}" class="inp" required>
          <label class="lbl">Method</label>
          <select name="method" class="inp">@foreach (\App\Models\Payment::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
          <label class="lbl">Reference (optional)</label>
          <input type="text" name="reference" class="inp">
          <label class="lbl">Payment slip</label>
          <input type="file" name="slip" accept="image/*" class="inp" required>
          <button class="btn ok">Upload Payment Slip</button>
        </form>
      @endif
    </div>

    @if ($booking->documents->isNotEmpty())
      <div class="card">
        <h3>Documents</h3>
        @foreach ($booking->documents as $doc)
          <a class="brow" href="{{ route('documents.download.portal', $doc) }}"><div class="n">📄 {{ $doc->typeLabel() }}</div><span class="badge b-info">PDF</span></a>
        @endforeach
      </div>
    @endif

    @if ($booking->status === 'confirmed' || $booking->amendments->isNotEmpty())
      <div class="card">
        <h3>Request Amendment</h3>

        @foreach ($booking->amendments as $am)
          <div class="sum" style="align-items:flex-start">
            <span style="color:var(--muted)">{{ $am->typeLabel() }}<br>
              <span style="font-size:11px">{{ $am->created_at->format('d M Y') }}</span></span>
            <span style="text-align:right">
              <span class="badge b-{{ $am->statusBadge() }}">{{ ucfirst($am->status) }}</span>
              <div style="font-size:11.5px;color:var(--muted);margin-top:3px">{{ $am->requested_value ?? optional($am->requested_date)->format('d M Y') }}</div>
              @if ($am->admin_note)<div style="font-size:11px;color:var(--muted)">“{{ $am->admin_note }}”</div>@endif
            </span>
          </div>
        @endforeach

        @if ($booking->status === 'confirmed' && ! $booking->openAmendment)
          <form method="POST" action="{{ route('agent.bookings.amendment', $booking) }}" style="margin-top:10px">
            @csrf
            <label class="lbl">Amendment type</label>
            <select name="type" id="amType" class="inp" onchange="amToggle()">
              @foreach (\App\Models\BookingAmendment::TYPES as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
              @endforeach
            </select>

            <div id="amDate">
              <label class="lbl">Requested new date</label>
              <input type="date" name="requested_date" class="inp" min="{{ now()->addDay()->format('Y-m-d') }}">
              @if ($booking->package?->bookableDates()->isNotEmpty())
                <label class="lbl">Or pick a scheduled departure</label>
                <select name="requested_package_date_id" class="inp">
                  <option value="">—</option>
                  @foreach ($booking->package->bookableDates() as $d)
                    <option value="{{ $d->id }}">{{ $d->depart_date?->format('d M Y') }}</option>
                  @endforeach
                </select>
              @endif
            </div>

            <div id="amPickup" style="display:none">
              <label class="lbl">New pickup location</label>
              <input type="text" name="requested_pickup_location" class="inp" placeholder="e.g. KLIA2 Gate C">
              <label class="lbl">New arrival time</label>
              <input type="time" name="requested_arrival_time" class="inp">
            </div>

            <label class="lbl">Reason <span style="color:var(--danger)">*</span></label>
            <textarea name="reason" rows="2" class="inp" required placeholder="Why does this need to change?"></textarea>

            <button class="btn">Submit Amendment Request</button>
          </form>

          <script>
            function amToggle() {
              const t = document.getElementById('amType').value;
              document.getElementById('amDate').style.display = t === 'travel_date' ? '' : 'none';
              document.getElementById('amPickup').style.display = t === 'pickup' ? '' : 'none';
            }
            amToggle();
          </script>
        @elseif ($booking->openAmendment)
          <div class="m" style="font-size:11.5px">An amendment is already awaiting HQ review — you'll be notified when it's decided.</div>
        @endif
      </div>
    @endif

    <div class="card">
      <h3>Timeline</h3>
      <ul class="tl">
        @foreach ($booking->timeline as $t)
          <li><div class="dot"></div><div><div class="a">{{ $t->action }}</div>@if($t->note)<div class="nt">{{ $t->note }}</div>@endif<div class="tm">{{ $t->created_at->format('d M Y, H:i') }}</div></div></li>
        @endforeach
      </ul>
    </div>
  </div>
@endsection
