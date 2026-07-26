@extends('layouts.agent')
@section('title', 'Edit ' . $booking->booking_no)

@php
  $flag = fn ($key) => $flagged && $flagged->isFlagged($key);
  $paxRows = data_get($payload, 'pax', []);
  if (! $paxRows) { $paxRows = [['key' => 'new-0', 'name' => '', 'type' => 'adult']]; }
@endphp

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.show', $booking) }}">‹</a>
    <div><div class="t">Edit Customer</div><div class="sub">{{ $booking->booking_no }}</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('agent.bookings.draft', $booking) }}" class="wrap" enctype="multipart/form-data">
    @csrf

    @if ($flagged)
      <div class="card" style="border-left:4px solid #b26a00;background:#fffaf2">
        <h3 style="color:#b26a00;margin-bottom:6px">📝 Admin asked you to fix this</h3>
        <div style="font-size:13px;line-height:1.5">{{ $flagged->remark }}</div>
      </div>
    @endif

    @if ($draft)
      <div class="card" style="border-left:4px solid var(--blue);background:#f5f8ff">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
          <div>
            <div style="font-weight:800;font-size:13px">Draft saved</div>
            <div class="m" style="font-size:11.5px">Last saved {{ $draft->updated_at->diffForHumans() }} · not sent to admin yet.</div>
          </div>
        </div>
        @if ($draft->isStale())
          <div class="m" style="font-size:11.5px;color:var(--danger);margin-top:8px">
            ⚠️ This booking changed while you were editing — check your changes before resubmitting.
          </div>
        @endif
      </div>
    @endif

    <div class="card">
      <h3>Customer Information</h3>

      <label class="lbl">Full name @if ($flag('customer.name'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="text" name="customer_name" class="inp @if ($flag('customer.name')) flagged @endif"
             value="{{ old('customer_name', data_get($payload, 'customer.name')) }}" required>

      <label class="lbl">Phone number @if ($flag('customer.phone'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="text" name="customer_phone" class="inp @if ($flag('customer.phone')) flagged @endif"
             value="{{ old('customer_phone', data_get($payload, 'customer.phone')) }}" required>

      <label class="lbl">Email @if ($flag('customer.email'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="email" name="customer_email" class="inp @if ($flag('customer.email')) flagged @endif"
             value="{{ old('customer_email', data_get($payload, 'customer.email')) }}">

      <label class="lbl">IC / Passport @if ($flag('customer.ic_passport_no'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="text" name="customer_ic" class="inp @if ($flag('customer.ic_passport_no')) flagged @endif"
             value="{{ old('customer_ic', data_get($payload, 'customer.ic_passport_no')) }}">
    </div>

    <div class="card">
      <h3>Travel Information</h3>

      <label class="lbl">Package @if ($flag('booking.package_id'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <select name="package_id" id="package_id" class="inp @if ($flag('booking.package_id')) flagged @endif" required>
        @foreach ($packages as $p)
          <option value="{{ $p->id }}" @selected(old('package_id', data_get($payload, 'booking.package_id')) == $p->id)>{{ $p->title }}</option>
        @endforeach
      </select>

      <div id="departureWrap">
        <label class="lbl">Departure date @if ($flag('booking.package_date_id'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
        <select name="package_date_id" id="package_date_id" class="inp"><option value="">—</option></select>
      </div>
      <div id="travelWrap">
        <label class="lbl">Travel date @if ($flag('booking.travel_date'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
        <input type="date" name="travel_date" id="travel_date" class="inp @if ($flag('booking.travel_date')) flagged @endif"
               value="{{ old('travel_date', data_get($payload, 'booking.travel_date')) }}">
      </div>
      <div class="m" id="dateNote" style="font-size:11.5px;margin:-6px 0 10px"></div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">Rooms &amp; Passengers @if ($flag('rooms'))<span style="color:#b26a00;font-size:11px">• admin requested this</span>@endif</h3>
        <button type="button" onclick="addRoom()"
                style="border:none;border-radius:9px;padding:7px 11px;font-size:12px;font-weight:800;background:#eef2fb;color:var(--blue)">＋ Room</button>
      </div>
      <div id="roomRows"></div>
      <div class="m" id="roomWarn" style="font-size:11.5px;color:var(--danger)"></div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">Passenger Details @if ($flag('pax'))<span style="color:#b26a00;font-size:11px">• admin requested this</span>@endif</h3>
        <button type="button" onclick="addPax()"
                style="border:none;border-radius:9px;padding:7px 11px;font-size:12px;font-weight:800;background:#eef2fb;color:var(--blue)">＋ Pax</button>
      </div>
      <div id="paxRows">
        @foreach ($paxRows as $i => $p)
          <div class="pax-row" style="border:1px solid var(--line);border-radius:13px;padding:11px;margin-bottom:10px">
            <input type="hidden" name="pax[{{ $i }}][key]" value="{{ data_get($p, 'key') }}">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
              <input type="text" name="pax[{{ $i }}][name]" class="inp" style="flex:1;margin:0"
                     placeholder="Name as per IC/passport" value="{{ data_get($p, 'name') }}">
              <button type="button" onclick="this.closest('.pax-row').remove()"
                      style="border:none;background:#fdeaea;color:#c22;border-radius:9px;padding:9px 11px;font-weight:800">✕</button>
            </div>
            <div class="row2">
              <div>
                <label class="lbl">Type</label>
                <select name="pax[{{ $i }}][type]" class="inp">
                  @foreach (['adult' => 'Adult', 'child' => 'Child', 'senior' => 'Senior', 'infant' => 'Infant'] as $k => $lbl)
                    <option value="{{ $k }}" @selected(data_get($p, 'type') === $k)>{{ $lbl }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="lbl">Age (years) @if ($flag('pax'))<span style="color:#b26a00">•</span>@endif</label>
                <input type="number" min="0" max="120" name="pax[{{ $i }}][age]"
                       class="inp @if ($flag('pax')) flagged @endif" value="{{ data_get($p, 'age') }}" placeholder="e.g. 6">
              </div>
            </div>
            <div class="row2">
              <div><label class="lbl">Date of birth</label><input type="date" name="pax[{{ $i }}][dob]" class="inp" value="{{ data_get($p, 'dob') }}"></div>
              <div></div>
            </div>
            <div class="row2">
              <div><label class="lbl">IC / Passport</label><input type="text" name="pax[{{ $i }}][ic_passport_no]" class="inp" value="{{ data_get($p, 'ic_passport_no') }}"></div>
              <div><label class="lbl">Nationality</label><input type="text" name="pax[{{ $i }}][nationality]" class="inp" value="{{ data_get($p, 'nationality') }}"></div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="m" style="font-size:11.5px">A child's date of birth sets their age at travel — admin often asks for this.</div>
    </div>

    <div class="card">
      <h3>Pickup Information</h3>
      <label class="lbl">Pickup location @if ($flag('booking.pickup_location'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      {{-- A datalist, not a <select>: the common pickups are offered as a dropdown but an
           agent can still type anything, since these are not a fixed list. --}}
      <input type="text" name="pickup_location" list="pickupOptions"
             class="inp @if ($flag('booking.pickup_location')) flagged @endif"
             value="{{ old('pickup_location', data_get($payload, 'booking.pickup_location')) }}" placeholder="Choose or type a location">
      <datalist id="pickupOptions">
        @foreach (['KLIA Terminal 1', 'KLIA Terminal 2', 'Subang Skypark', 'Penang International Airport',
                   'Kota Bharu Airport', 'Kuching International Airport', 'Kota Kinabalu Airport',
                   'Johor Bahru — Senai Airport', 'Hotel lobby', 'Customer residence'] as $spot)
          <option value="{{ $spot }}"></option>
        @endforeach
      </datalist>

      <label class="lbl">Arrival time @if ($flag('booking.arrival_time'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="time" name="arrival_time" class="inp @if ($flag('booking.arrival_time')) flagged @endif"
             value="{{ old('arrival_time', data_get($payload, 'booking.arrival_time')) }}">
    </div>

    <div class="card">
      <h3>Payment Information</h3>
      <label class="lbl">Deposit paid @if ($flag('payment.amount'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <input type="number" step="0.01" min="0" name="payment_amount" class="inp @if ($flag('payment.amount')) flagged @endif"
             value="{{ old('payment_amount', data_get($payload, 'payment.amount')) }}">

      <label class="lbl">Payment method @if ($flag('payment.method'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <select name="payment_method" class="inp @if ($flag('payment.method')) flagged @endif">
        <option value="">—</option>
        @foreach (\App\Models\Payment::METHODS as $k => $lbl)
          <option value="{{ $k }}" @selected(old('payment_method', data_get($payload, 'payment.method')) === $k)>{{ $lbl }}</option>
        @endforeach
      </select>

      <label class="lbl">Reference</label>
      <input type="text" name="payment_reference" class="inp" value="{{ old('payment_reference', data_get($payload, 'payment.reference')) }}">

      <label class="lbl">Payment receipt @if ($flag('payment.slip'))<span style="color:#b26a00">• admin requested this</span>@endif</label>
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        @if (data_get($payload, 'payment.slip_path'))
          <a href="{{ $draft && $draft->value('payment.slip_path') === data_get($payload, 'payment.slip_path')
                       ? route('agent.bookings.draft-slip', $booking)
                       : ($booking->payments->firstWhere('slip_path', data_get($payload, 'payment.slip_path'))
                           ? route('payments.slip', $booking->payments->firstWhere('slip_path', data_get($payload, 'payment.slip_path')))
                           : route('agent.bookings.draft-slip', $booking)) }}"
             target="_blank" class="btn" style="flex:1;padding:9px;font-size:12.5px;background:#eef1f8;color:var(--ink)">View</a>
        @endif
        <button type="button" onclick="document.getElementById('slipInput').click()" class="btn"
                style="flex:1;padding:9px;font-size:12.5px">Replace File</button>
      </div>
      <input type="file" name="slip" id="slipInput" accept="image/*" class="inp" style="display:none"
             onchange="document.getElementById('slipName').textContent = this.files[0] ? this.files[0].name : ''">
      <div class="m" id="slipName" style="font-size:11.5px;font-weight:700;color:var(--blue)"></div>
      <div class="m" style="font-size:11.5px">A new file replaces the receipt on your next submit. The old one is kept for the record.</div>
    </div>

    <div class="card">
      <h3>Agent Note @if ($flag('booking.notes'))<span style="color:#b26a00;font-size:11px">• admin requested this</span>@endif</h3>
      <textarea name="notes" rows="3" class="inp @if ($flag('booking.notes')) flagged @endif" placeholder="Anything admin should know…">{{ old('notes', data_get($payload, 'booking.notes')) }}</textarea>
    </div>

    <div class="card">
      <h3>Summary</h3>
      <div id="s-rooms"></div>
      <div class="sum"><span>Total pax</span><span id="s-pax">0</span></div>
      <div class="sum total"><span>Estimated total</span><span id="s-total">RM 0</span></div>
      <div class="m" style="font-size:11.5px">Final pricing is confirmed when you resubmit.</div>
    </div>

    <div style="display:flex;gap:9px;margin-bottom:10px">
      {{-- formnovalidate: a draft is work in progress. Client-side `required` (e.g. the
           departure on a fixed-date package) must not stop an agent saving and coming
           back later — completeness is enforced at resubmit, not at save. --}}
      <button class="btn" formnovalidate formaction="{{ route('agent.bookings.draft', $booking) }}"
              style="flex:1;background:#eef1f8;color:var(--ink)">💾 Save as Draft</button>
      <button class="btn" formaction="{{ route('agent.bookings.review', $booking) }}" style="flex:1">Review Changes ›</button>
    </div>
  </form>

  @if ($draft)
    <form method="POST" action="{{ route('agent.bookings.draft.discard', $booking) }}" class="wrap" style="margin-top:-10px">
      @csrf @method('DELETE')
      <button class="btn" style="background:#fdeaea;color:#c22;margin-bottom:20px">Discard draft</button>
    </form>
  @endif

  <style>.inp.flagged{border-color:#b26a00;background:#fffaf2}</style>

  @include('agent.bookings._rooms-js', ['presetRooms' => old('rooms', data_get($payload, 'rooms', []))])

  <script>
    let paxIdx = {{ count($paxRows) }};
    function addPax() {
      const i = paxIdx++;
      const row = document.createElement('div');
      row.className = 'pax-row';
      row.style.cssText = 'border:1px solid var(--line);border-radius:13px;padding:11px;margin-bottom:10px';
      row.innerHTML = `
        <input type="hidden" name="pax[${i}][key]" value="new-${i}">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
          <input type="text" name="pax[${i}][name]" class="inp" style="flex:1;margin:0" placeholder="Name as per IC/passport">
          <button type="button" onclick="this.closest('.pax-row').remove()"
                  style="border:none;background:#fdeaea;color:#c22;border-radius:9px;padding:9px 11px;font-weight:800">✕</button>
        </div>
        <div class="row2">
          <div><label class="lbl">Type</label><select name="pax[${i}][type]" class="inp"><option value="adult">Adult</option><option value="child">Child</option><option value="senior">Senior</option><option value="infant">Infant</option></select></div>
          <div><label class="lbl">Age (years)</label><input type="number" min="0" max="120" name="pax[${i}][age]" class="inp" placeholder="e.g. 6"></div>
        </div>
        <div class="row2">
          <div><label class="lbl">Date of birth</label><input type="date" name="pax[${i}][dob]" class="inp"></div>
          <div></div>
        </div>
        <div class="row2">
          <div><label class="lbl">IC / Passport</label><input type="text" name="pax[${i}][ic_passport_no]" class="inp"></div>
          <div><label class="lbl">Nationality</label><input type="text" name="pax[${i}][nationality]" class="inp"></div>
        </div>`;
      document.getElementById('paxRows').appendChild(row);
    }

    bootRooms(@json(data_get($payload, 'booking.package_date_id')));
  </script>
@endsection
