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

    @php
      $deposit = $booking->originalDeposit();
      $canPay  = ! in_array($booking->status, ['cancelled', 'rejected', 'refunded']);
    @endphp

    <div class="card">
      <h3>Payment Information</h3>
      @if ($deposit)
        <div style="background:#f6f8fd;border-radius:13px;padding:13px;display:flex;gap:14px;flex-wrap:wrap">
          <div style="flex:1 1 130px">
            <div class="m" style="font-size:11.5px;color:var(--muted)">Original Deposit Paid</div>
            <div style="font-size:21px;font-weight:800;margin:3px 0 6px">RM {{ number_format($deposit->amount, 2) }}</div>
            <span class="badge b-{{ $deposit->statusBadge() }}">{{ $deposit->statusLabel() }}</span>
          </div>
          <div style="flex:1 1 130px">
            <div class="m" style="font-size:11.5px;color:var(--muted)">Payment Method</div>
            <div style="font-weight:700;font-size:13px;margin-bottom:8px">{{ $deposit->methodLabel() }}</div>
            <div class="m" style="font-size:11.5px;color:var(--muted)">Reference</div>
            <div style="font-weight:700;font-size:13px">{{ $deposit->reference ?: '—' }}</div>
          </div>
        </div>
      @else
        <div class="m" style="font-size:12px">No payment recorded on this booking yet.</div>
      @endif
    </div>

    @if ($canPay)
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div style="display:flex;gap:10px;align-items:center">
            <span style="width:32px;height:32px;border-radius:10px;background:#e6f0ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800">＋</span>
            <div>
              <h3 style="margin:0">Add Deposit</h3>
              <div class="m" style="font-size:11.5px;color:var(--muted)">Record a new deposit payment</div>
            </div>
          </div>
          <button type="button" id="depToggle" onclick="toggleDeposit()"
                  style="border:none;width:36px;height:36px;border-radius:50%;background:var(--blue);color:#fff;font-size:19px;font-weight:800">＋</button>
        </div>

        {{-- Stays open after a validation bounce, otherwise the errors point at a hidden form. --}}
        <div id="depForm" style="display:{{ $errors->any() && old('amount') !== null ? '' : 'none' }};margin-top:14px">
          <form method="POST" action="{{ route('agent.bookings.payment', $booking) }}" enctype="multipart/form-data">
            @csrf
            <div class="row2">
              <div>
                {{-- Left blank on purpose: an instalment is rarely the whole balance, and a
                     pre-filled figure gets submitted unread. The agent types what they took. --}}
                <label class="lbl">Amount (RM)</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="inp"
                       placeholder="Enter amount" required>
              </div>
              <div>
                <label class="lbl">Payment Method</label>
                {{-- This form IS the bank-transfer path, so it may not default to FPX (first option). --}}
                <select name="method" class="inp">
                  @foreach (\App\Models\Payment::METHODS as $k => $label)
                    <option value="{{ $k }}" @selected(old('method', 'slip_upload') === $k)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <label class="lbl">Reference</label>
            <input type="text" name="reference" value="{{ old('reference') }}" class="inp" placeholder="e.g. Bank reference, Cash, Office">
            <label class="lbl">Upload Receipt</label>
            <input type="file" name="slip" accept="image/*" class="inp" required>
            <div class="row2" style="margin-top:4px">
              <button type="button" class="btn ghost" onclick="toggleDeposit()">Cancel</button>
              <button class="btn">Save Deposit</button>
            </div>
          </form>
        </div>
      </div>
    @endif

    <div class="card" id="deposit-summary">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
        <span style="width:28px;height:28px;border-radius:9px;background:#e6f0ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:800">≡</span>
        <div style="flex:1">
          <h3 style="margin:0">Deposit Summary</h3>
          <div class="m" style="font-size:11.5px;color:var(--muted)">All deposits made for this booking</div>
        </div>
        {{-- These figures move when STAFF verify a slip, so the agent needs to pull the latest.
             It reloads rather than linking to the same URL — a link whose only difference is
             the #fragment just scrolls, it never re-requests the page. --}}
        <a href="{{ route('agent.bookings.show', $booking) }}" title="Refresh totals" id="depRefresh"
           onclick="event.preventDefault(); this.textContent='⏳'; location.reload()"
           style="width:34px;height:34px;border-radius:50%;background:#eef2fb;color:var(--blue);display:flex;
                  align-items:center;justify-content:center;font-size:16px;font-weight:800;text-decoration:none">↻</a>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:9px">
        @foreach ([
          ['Original Deposit', $deposit->amount ?? 0, 'var(--ink)', '#f6f8fd'],
          ['Additional Deposits', $booking->additionalDepositsTotal(), 'var(--ok)', '#f1fbf5'],
          ['Total Paid', $booking->recordedTotal(), 'var(--blue)', '#eef4ff'],
          ['Outstanding Balance', $booking->outstandingRecorded(), '#e07a1f', '#fff7ed'],
        ] as [$label, $value, $colour, $bg])
          <div style="background:{{ $bg }};border:1px solid var(--line);border-radius:13px;padding:11px;text-align:center">
            <div class="m" style="font-size:10.5px;color:var(--muted);line-height:1.3">{{ $label }}</div>
            <div style="font-weight:800;font-size:15px;color:{{ $colour }};margin-top:4px">RM {{ number_format($value, 2) }}</div>
          </div>
        @endforeach
      </div>

      {{-- Recorded ≠ verified: the tiles count what was filed, the booking's paid figure
           only moves once staff check the slip. Saying so avoids "I already paid" disputes. --}}
      @if ($booking->pendingVerificationTotal() > 0)
        <div class="m" style="font-size:11.5px;color:var(--muted);margin-top:10px;line-height:1.5">
          RM {{ number_format($booking->pendingVerificationTotal(), 2) }} is still awaiting staff verification —
          only RM {{ number_format($booking->paid_amount, 2) }} has been confirmed so far.
        </div>
      @endif
      @if ($booking->forfeited_amount > 0)
        <div class="sum" style="margin-top:10px"><span style="color:var(--muted)">Cancellation charge ({{ $booking->forfeited_packs }} pack)</span><span style="font-weight:700;color:var(--danger)">− RM {{ number_format($booking->forfeited_amount, 2) }}</span></div>
      @endif
      @if ($booking->refundableAmount() > 0)
        <div class="sum"><span style="color:var(--muted)">Refundable</span><span style="font-weight:700;color:var(--ok)">RM {{ number_format($booking->refundableAmount(), 2) }}</span></div>
      @endif
    </div>

    @if ($booking->payments->isNotEmpty())
      <div class="card">
        <h3>Payment History</h3>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:11.5px">
            <thead>
              <tr style="color:var(--muted);text-align:left">
                <th style="padding:7px 6px;font-weight:700">Date</th>
                <th style="padding:7px 6px;font-weight:700">Amount</th>
                <th style="padding:7px 6px;font-weight:700">Method</th>
                <th style="padding:7px 6px;font-weight:700">Reference</th>
                <th style="padding:7px 6px;font-weight:700;text-align:right">Receipt</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($booking->payments->sortByDesc('id') as $p)
                <tr style="border-top:1px solid var(--line)">
                  <td style="padding:9px 6px;white-space:nowrap">{{ optional($p->paid_at ?? $p->created_at)->format('d M Y') }}</td>
                  <td style="padding:9px 6px;font-weight:800;white-space:nowrap">
                    RM {{ number_format($p->amount, 2) }}
                    <div><span class="badge b-{{ $p->statusBadge() }}" style="font-size:9.5px;padding:2px 7px">{{ $p->statusLabel() }}</span></div>
                  </td>
                  <td style="padding:9px 6px"><span class="badge b-{{ $p->methodBadge() }}">{{ $p->methodShort() }}</span></td>
                  <td style="padding:9px 6px;color:var(--muted)">{{ $p->reference ?: '—' }}</td>
                  <td style="padding:9px 4px;text-align:right">
                    @if ($p->slip_path)
                      <a href="{{ route('payments.slip', $p) }}" target="_blank" style="color:var(--blue);font-weight:800">View</a>
                    @else
                      <span style="color:var(--muted)">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    <div class="card">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px">
        <span style="width:28px;height:28px;border-radius:9px;background:#efe9ff;color:#6b3df5;display:flex;align-items:center;justify-content:center;font-weight:800">✎</span>
        <h3 style="margin:0">Agent Note</h3>
      </div>
      <form method="POST" action="{{ route('agent.bookings.note', $booking) }}">
        @csrf
        <textarea name="agent_note" rows="3" class="inp" placeholder="Anything admin should know…">{{ old('agent_note', $booking->agent_note) }}</textarea>
        <button class="btn ghost" style="margin-top:2px">Save Note</button>
      </form>
    </div>

    @php($portalDocs = $booking->shareableDocuments())
    @if ($portalDocs->isNotEmpty())
      <div class="card">
        <h3>Documents</h3>
        @foreach ($portalDocs as $doc)
          <a class="brow" href="{{ route('documents.download.portal', $doc) }}"><div class="n">📄 {{ $doc->typeLabel() }}</div><span class="badge b-info">PDF</span></a>
        @endforeach
      </div>
    @endif

    @if ($booking->isAmendable() || $booking->amendments->isNotEmpty())
      <div class="card">
        <h3>Request Amendment</h3>

        @foreach ($booking->amendments as $am)
          <div class="sum" style="align-items:flex-start">
            <span style="color:var(--muted)">{{ $am->typeLabel() }}<br>
              <span style="font-size:11px">{{ $am->created_at->format('d M Y') }}</span></span>
            <span style="text-align:right">
              <span class="badge b-{{ $am->statusBadge() }}">{{ ucfirst($am->status) }}</span>
              <div style="font-size:11.5px;color:var(--muted);margin-top:3px">{{ $am->requestedLabel() }}</div>
              @if ($am->attachment_path)
                <div style="font-size:11px;margin-top:2px"><a href="{{ route('amendments.attachment', $am) }}" target="_blank">📎 Supporting document</a></div>
              @endif
              @if ($am->admin_note)<div style="font-size:11px;color:var(--muted)">“{{ $am->admin_note }}”</div>@endif
            </span>
          </div>
        @endforeach

        @if ($booking->isAmendable() && ! $booking->openAmendment)
          <form method="POST" action="{{ route('agent.bookings.amendment', $booking) }}" enctype="multipart/form-data" style="margin-top:10px">
            @csrf
            <label class="lbl">Amendment type</label>
            <select name="type" id="amType" class="inp" onchange="amToggle()">
              @foreach (\App\Models\BookingAmendment::TYPES as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
              @endforeach
            </select>

            <div id="amDate">
              {{-- Already postponed: the only useful request left is a real date. --}}
              @unless ($booking->isPostponed())
                <label class="lbl" style="display:flex;align-items:center;gap:7px">
                  <input type="checkbox" name="is_postponement" value="1" id="amPostpone" onchange="amToggle()" style="width:auto;margin:0">
                  <span>Postpone — customer has not picked a new date yet</span>
                </label>
              @endunless

              <div id="amDateFields">
                <label class="lbl">Requested new date</label>
                <input type="date" name="requested_date" id="amNewDate" class="inp" min="{{ now()->addDay()->format('Y-m-d') }}">
                @if ($booking->package?->bookableDates()->isNotEmpty())
                  <label class="lbl">Or pick a scheduled departure</label>
                  <select name="requested_package_date_id" id="amDeparture" class="inp">
                    <option value="">—</option>
                    @foreach ($booking->package->bookableDates() as $d)
                      <option value="{{ $d->id }}">{{ $d->depart_date?->format('d M Y') }}</option>
                    @endforeach
                  </select>
                @endif
              </div>

              <div id="amPostponeNote" class="m" style="font-size:11.5px;display:none">
                The trip goes on hold as <b>Postponed</b> — seats are released and no travel date is
                shown until you send a new one. Nothing is cancelled and nothing is refunded.
              </div>

              {{-- Client rule: a date change is not reviewable on the agent's word alone. --}}
              <label class="lbl">Supporting document <span style="color:var(--danger)">*</span></label>
              <input type="file" name="attachment" id="amFile" class="inp" accept=".pdf,.jpg,.jpeg,.png">
              <div class="m" style="font-size:11px">
                Required for a date change — the customer's message, medical note or letter
                explaining the reason. PDF or image, max 8 MB.
              </div>
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
              const isDate = t === 'travel_date';
              const pBox = document.getElementById('amPostpone');
              const postpone = pBox ? pBox.checked : false;

              document.getElementById('amDate').style.display = isDate ? '' : 'none';
              document.getElementById('amPickup').style.display = t === 'pickup' ? '' : 'none';

              // A postponement IS the absence of a date, so the date fields go away and
              // are cleared — a stale value left in a hidden input would still post.
              document.getElementById('amDateFields').style.display = postpone ? 'none' : '';
              document.getElementById('amPostponeNote').style.display = postpone ? '' : 'none';
              if (postpone) {
                document.getElementById('amNewDate').value = '';
                const dep = document.getElementById('amDeparture');
                if (dep) dep.value = '';
              }

              // Chrome refuses to submit a form with a `required` field it cannot focus,
              // so the attribute is toggled with visibility, never left on a hidden input.
              document.getElementById('amFile').required = isDate;
            }
            amToggle();
          </script>
        @elseif ($booking->openAmendment)
          <div class="m" style="font-size:11.5px">An amendment is already awaiting HQ review — you'll be notified when it's decided.</div>
        @endif
      </div>
    @endif

    @if ($booking->isCancellableByAgent())
      <div class="card" style="border:1px solid var(--danger)">
        <h3 style="color:var(--danger)">Cancel Booking</h3>
        @if ($booking->chargeablePacks() > 0 && $booking->paid_amount > 0)
          <div class="sum"><span style="color:var(--muted)">Packs to cancel</span><span style="font-weight:700">{{ $booking->chargeablePacks() }}</span></div>
          <div class="sum"><span style="color:var(--muted)">Rate per pack</span><span style="font-weight:700">RM {{ number_format($booking->package?->cancellationFeePerPack() ?? 0, 2) }}</span></div>
          <div class="sum total"><span>Will be forfeited</span><span style="color:var(--danger)">RM {{ number_format($booking->chargeablePacks() * ($booking->package?->cancellationFeePerPack() ?? 0), 2) }}</span></div>
          <div class="m" style="font-size:11.5px;line-height:1.6;margin-top:6px">
            This is deducted from the RM {{ number_format($booking->paid_amount, 2) }} already paid.
            <strong>HQ processes any refund</strong> — you cannot pay money back from here.
          </div>
        @else
          <div class="m" style="font-size:11.5px;line-height:1.6">
            Nothing has been paid on this booking, so cancelling it costs the customer nothing.
          </div>
        @endif

        <form method="POST" action="{{ route('agent.bookings.cancel', $booking) }}" style="margin-top:12px">
          @csrf
          <label class="lbl">Reason <span style="color:var(--danger)">*</span></label>
          <textarea name="reason" rows="2" class="inp" required placeholder="Why is the customer cancelling?"></textarea>
          <label class="lbl">Type <strong>CANCEL</strong> to confirm</label>
          <input type="text" name="confirm" class="inp" placeholder="CANCEL" autocomplete="off" required>
          <button class="btn" style="background:var(--danger)">Cancel This Booking</button>
        </form>
      </div>
    @endif

    <script>
      function toggleDeposit() {
        const box = document.getElementById('depForm');
        const open = box.style.display === 'none';
        box.style.display = open ? '' : 'none';
        document.getElementById('depToggle').textContent = open ? '−' : '＋';
        if (open) box.querySelector('input[name=amount]').focus();
      }
    </script>

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
