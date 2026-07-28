@extends('layouts.agent')
@section('title', 'New Booking')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.index') }}">‹</a>
    <div><div class="t">New Booking</div><div class="sub">Submit for HQ verification</div></div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('agent.bookings.store') }}" class="wrap" data-select2 enctype="multipart/form-data">
    @csrf
    <div class="card">
      <h3>Package</h3>
      <label class="lbl">Select package <span class="req">*</span></label>
      <select name="package_id" id="package_id" class="inp" required>
        <option value="">Choose…</option>
        @foreach ($packages as $p)<option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->title }}</option>@endforeach
      </select>
      <div id="departureWrap">
        <label class="lbl">Departure date <span class="req">*</span></label>
        <select name="package_date_id" id="package_date_id" class="inp"><option value="">—</option></select>
      </div>
      {{-- Check-in and check-out sit side by side, as specified. On a scheduled departure
           both are filled from it and locked — the schedule owns them. --}}
      <div id="travelWrap" class="row2">
        <div>
          <label class="lbl">Check-in Date <span class="req">*</span></label>
          <input type="date" name="travel_date" id="travel_date" value="{{ old('travel_date') }}" class="inp" min="{{ now()->addDay()->format('Y-m-d') }}">
        </div>
        <div>
          <label class="lbl">Check-out Date</label>
          <input type="date" name="return_date" id="return_date" value="{{ old('return_date') }}" class="inp" min="{{ now()->addDays(2)->format('Y-m-d') }}">
        </div>
      </div>
      <div class="m" id="dateNote" style="font-size:11.5px;margin:-6px 0 10px"></div>
      <label class="lbl">Pickup location</label>
      <input type="text" name="pickup_location" value="{{ old('pickup_location') }}" class="inp" placeholder="e.g. KLIA2 Gate C, Level 2">
      <label class="lbl">Arrival time</label>
      <input type="time" name="arrival_time" value="{{ old('arrival_time') }}" class="inp">
      <input type="hidden" name="type" value="online">
    </div>

    <div class="card">
      <h3>Customer</h3>

      @if ($customers->isNotEmpty())
        <label class="lbl">Select your customer <span class="req">*</span></label>
        {{-- The "new customer" entry carries a real sentinel value, not "". Select2 treats
             every empty-value option as the placeholder and drops it from the list, which
             hid this option completely. `__new` is normalised back to null in store(). --}}
        <select name="customer_id" id="customer_id" class="inp" onchange="toggleNewCustomer()">
          <option value="">Choose…</option>
          @foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach
          <option value="__new" data-new="1" @selected(old('new_customer_name'))>➕ New customer…</option>
        </select>
      @else
        <div class="m" style="font-size:12px;margin-bottom:10px">You have no customers yet — fill in their details below and they'll be registered to you.</div>
      @endif

      <div id="newCustomer">
        <label class="lbl">Name <span class="req">*</span></label>
        <input type="text" name="new_customer_name" id="new_customer_name" value="{{ old('new_customer_name') }}" class="inp" placeholder="Name as per IC/passport">

        <label class="lbl">Phone No. <span class="req">*</span></label>
        <input type="text" name="new_customer_phone" id="new_customer_phone" value="{{ old('new_customer_phone') }}" class="inp" placeholder="01X-XXX XXXX">

        <label class="lbl">Email (optional)</label>
        <input type="email" name="new_customer_email" id="new_customer_email" value="{{ old('new_customer_email') }}" class="inp" placeholder="you@email.com">
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">Rooms &amp; Passengers <span class="req">*</span></h3>
        <button type="button" onclick="addRoom()"
                style="border:none;border-radius:9px;padding:7px 11px;font-size:12px;font-weight:800;background:#eef2fb;color:var(--blue)">＋ Room</button>
      </div>
      <div class="m" style="font-size:11.5px;margin-bottom:10px">Each line is one room. <strong>It cannot hold more pax than its type allows</strong> — a 2-pax room takes 2, not 3. Fewer is fine. Need more beds? Add another room. Infants share a bed and are not counted.</div>
      <div id="roomRows"></div>
      <div class="m" id="roomWarn" style="font-size:11.5px;color:var(--danger)"></div>

      <label class="lbl">Coupon code (optional)</label>
      <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="inp" style="text-transform:uppercase" placeholder="e.g. RAYA2026">
      <label class="lbl">Notes (optional)</label>
      <textarea name="notes" rows="2" class="inp" placeholder="Special requests…">{{ old('notes') }}</textarea>
    </div>

    <div class="card">
      <h3>Deposit</h3>
      <div class="m" style="font-size:11.5px;margin-bottom:10px">A booking cannot be submitted without the deposit receipt.</div>
      <label class="lbl">Deposit amount (RM) <span class="req">*</span></label>
      <input type="number" name="deposit_amount" id="deposit_amount" step="0.01" min="0.01"
             value="{{ old('deposit_amount') }}" class="inp" placeholder="0.00" required>
      <label class="lbl">Method</label>
      <select name="deposit_method" class="inp" data-no-select2>
        @foreach (\App\Models\Payment::METHODS as $k => $label)<option value="{{ $k }}" @selected(old('deposit_method', 'slip_upload') === $k)>{{ $label }}</option>@endforeach
      </select>
      <label class="lbl">Reference (optional)</label>
      <input type="text" name="deposit_reference" value="{{ old('deposit_reference') }}" class="inp" placeholder="Transfer ref no.">
      <label class="lbl">Deposit receipt <span class="req">*</span></label>
      <input type="file" name="deposit_slip" id="deposit_slip" accept="image/*" class="inp" required>
    </div>

    <div class="card">
      <h3>Summary</h3>
      <div id="s-rooms"></div>
      <div class="sum"><span>Total pax</span><span id="s-pax">0</span></div>
      <div class="sum total"><span>Total</span><span id="s-total">RM 0</span></div>
    </div>

    <div class="m" id="submitHint" style="font-size:11.5px;color:var(--danger);margin-bottom:8px"></div>
    <button class="btn" id="submitBtn" style="margin-bottom:20px" disabled>Submit Booking</button>
  </form>

  @include('agent.bookings._rooms-js', ['presetRooms' => old('rooms', [])])

  <script>
    // With no customers the inline fields are the only way to name one, so they stay open.
    function toggleNewCustomer(){
      const sel = $('customer_id');
      const on = !sel || sel.value === '__new' || !!sel.selectedOptions[0]?.dataset.new;
      $('newCustomer').style.display = on ? '' : 'none';
      $('new_customer_name').required = on;
      $('new_customer_phone').required = on;
      if (window.gateSubmit) gateSubmit();
    }
    toggleNewCustomer();

    // Every field marked * has to be in before the form will submit — the pack lines, the
    // customer, a booked date and the deposit receipt. recalc() calls this on every room
    // edit; the date + deposit fields are wired below. The server re-checks all of it.
    function gateSubmit() {
      const pax = +$('s-pax').textContent || 0;
      const dateOk = !!$('package_date_id').value || !!$('travel_date').value;
      const amount = parseFloat($('deposit_amount').value) || 0;
      const receiptOk = amount > 0 && $('deposit_slip').files.length > 0;
      // Either an existing customer is picked, or the inline one is named.
      const sel = $('customer_id');
      const customerOk = sel && sel.value && sel.value !== '__new'
        ? true
        : !!$('new_customer_name').value.trim() && !!$('new_customer_phone').value.trim();

      const missing = [];
      if (!$('package_id').value) missing.push('package');
      if (pax < 1) missing.push('pack details');
      // Set by recalc(): every room line must hold exactly its tier's occupancy.
      else if (window.roomCapacityOk === false) missing.push('room capacity (see the red line above)');
      if (!customerOk) missing.push('customer');
      if (!dateOk) missing.push('booked date');
      if (!receiptOk) missing.push('deposit receipt + amount');

      $('submitBtn').disabled = missing.length > 0;
      $('submitBtn').style.opacity = missing.length ? '.45' : '';
      $('submitHint').textContent = missing.length ? 'Still needed: ' + missing.join(' · ') : '';
    }
    ['package_id', 'package_date_id', 'travel_date', 'return_date', 'deposit_amount', 'deposit_slip',
     'customer_id', 'new_customer_name', 'new_customer_phone']
      .filter(id => $(id))
      .forEach(id => { $(id).addEventListener('change', gateSubmit); $(id).addEventListener('input', gateSubmit); });

    bootRooms();
    gateSubmit();
  </script>
@endsection
