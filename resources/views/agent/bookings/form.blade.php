@extends('layouts.agent')
@section('title', 'New Booking')

@php
  $pkgJson = $packages->map(fn ($p) => [
    'id' => $p->id,
    'pricings' => $p->pricings->map(fn ($pr) => [
      'id' => $pr->id, 'tier_name' => $pr->tier_name, 'capacity' => (int) $pr->capacity,
      'adult_price' => (float) ($pr->promo_price ?? $pr->adult_price),
      'child_price' => (float) $pr->child_price,
      'senior_price' => (float) ($pr->senior_price ?: ($pr->promo_price ?? $pr->adult_price)),
      'infant_price' => (float) $pr->infant_price,
      'is_default' => (bool) $pr->is_default,
    ])->values(),
    'date_mode' => $p->date_mode,
    'dates' => $p->bookableDates()->map(fn ($d) => [
      'id' => $d->id,
      'label' => $d->depart_date?->format('d M Y') . ($d->return_date ? ' → ' . $d->return_date->format('d M Y') : ''),
      'depart' => $d->depart_date?->format('Y-m-d'),
      'seats' => $d->seats_total > 0 ? $d->seatsAvailable() : null,
    ])->values(),
  ])->keyBy('id');
@endphp

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.bookings.index') }}">‹</a>
    <div><div class="t">New Booking</div><div class="sub">Submit for HQ verification</div></div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('agent.bookings.store') }}" class="wrap">
    @csrf
    <div class="card">
      <h3>Package</h3>
      <label class="lbl">Select package</label>
      <select name="package_id" id="package_id" class="inp" required>
        <option value="">Choose…</option>
        @foreach ($packages as $p)<option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->title }}</option>@endforeach
      </select>
      <div id="departureWrap">
        <label class="lbl">Departure date</label>
        <select name="package_date_id" id="package_date_id" class="inp"><option value="">—</option></select>
      </div>
      <div id="travelWrap">
        <label class="lbl">Travel date</label>
        <input type="date" name="travel_date" id="travel_date" value="{{ old('travel_date') }}" class="inp" min="{{ now()->addDay()->format('Y-m-d') }}">
      </div>
      <div class="m" id="dateNote" style="font-size:11.5px;margin:-6px 0 10px"></div>
      <input type="hidden" name="type" value="online">
    </div>

    <div class="card">
      <h3>Customer</h3>

      @if ($customers->isNotEmpty())
        <label class="lbl">Select your customer</label>
        <select name="customer_id" id="customer_id" class="inp" onchange="toggleNewCustomer()">
          <option value="">Choose…</option>
          @foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach
          <option value="" data-new="1" @selected(old('new_customer_name'))>➕ New customer…</option>
        </select>
      @else
        <div class="m" style="font-size:12px;margin-bottom:10px">You have no customers yet — fill in their details below and they'll be registered to you.</div>
      @endif

      <div id="newCustomer">
        <label class="lbl">Name</label>
        <input type="text" name="new_customer_name" id="new_customer_name" value="{{ old('new_customer_name') }}" class="inp" placeholder="Name as per IC/passport">

        <label class="lbl">Phone No.</label>
        <input type="text" name="new_customer_phone" id="new_customer_phone" value="{{ old('new_customer_phone') }}" class="inp" placeholder="01X-XXX XXXX">

        <label class="lbl">Email (optional)</label>
        <input type="email" name="new_customer_email" id="new_customer_email" value="{{ old('new_customer_email') }}" class="inp" placeholder="you@email.com">
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">Rooms &amp; Passengers</h3>
        <button type="button" onclick="addRoom()"
                style="border:none;border-radius:9px;padding:7px 11px;font-size:12px;font-weight:800;background:#eef2fb;color:var(--blue)">＋ Room</button>
      </div>
      <div class="m" style="font-size:11.5px;margin-bottom:10px">Each line is one room type. Add a line per room type the party needs — rates differ by occupancy.</div>
      <div id="roomRows"></div>
      <div class="m" id="roomWarn" style="font-size:11.5px;color:var(--danger)"></div>

      <label class="lbl">Coupon code (optional)</label>
      <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="inp" style="text-transform:uppercase" placeholder="e.g. RAYA2026">
      <label class="lbl">Notes (optional)</label>
      <textarea name="notes" rows="2" class="inp" placeholder="Special requests…">{{ old('notes') }}</textarea>
    </div>

    <div class="card">
      <h3>Summary</h3>
      <div id="s-rooms"></div>
      <div class="sum"><span>Total pax</span><span id="s-pax">0</span></div>
      <div class="sum total"><span>Total</span><span id="s-total">RM 0</span></div>
    </div>

    <button class="btn" style="margin-bottom:20px">Submit Booking</button>
  </form>

  <script>
    const PKGS = @json($pkgJson);
    const $ = id => document.getElementById(id);
    const money = n => 'RM ' + (Number(n) || 0).toLocaleString('en-MY', {maximumFractionDigits: 0});
    const OLD_ROOMS = @json(old('rooms', []));
    let roomIdx = 0;

    // One row per room type. Rates are per pax and differ by occupancy, so the row
    // has to carry its own pax split rather than sharing one booking-wide count.
    function addRoom(preset) {
      const pkg = PKGS[$('package_id').value];
      if (!pkg || !pkg.pricings.length) { $('roomWarn').textContent = 'Choose a package first.'; return; }
      $('roomWarn').textContent = '';

      const i = roomIdx++;
      const opts = pkg.pricings.map(p =>
        `<option value="${p.id}" ${preset && preset.package_pricing_id == p.id ? 'selected' : (!preset && p.is_default ? 'selected' : '')}>`
        + `${p.tier_name} (${p.capacity} pax) — ${money(p.adult_price)}/pax</option>`).join('');

      const row = document.createElement('div');
      row.className = 'room-row';
      row.style.cssText = 'border:1px solid var(--line);border-radius:13px;padding:11px;margin-bottom:10px';
      row.innerHTML = `
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
          <select name="rooms[${i}][package_pricing_id]" class="inp room-type" style="flex:1;margin:0">${opts}</select>
          <button type="button" onclick="this.closest('.room-row').remove();recalc()"
                  style="border:none;background:#fdeaea;color:#c22;border-radius:9px;padding:9px 11px;font-weight:800">✕</button>
        </div>
        <div class="row2">
          <div><label class="lbl">Adults</label><input type="number" min="0" class="inp room-n" name="rooms[${i}][adults]" value="${preset ? (preset.adults || 0) : (i === 0 ? 1 : 0)}"></div>
          <div><label class="lbl">Children</label><input type="number" min="0" class="inp room-n" name="rooms[${i}][children]" value="${preset ? (preset.children || 0) : 0}"></div>
        </div>
        <div class="row2">
          <div><label class="lbl">Seniors</label><input type="number" min="0" class="inp room-n" name="rooms[${i}][seniors]" value="${preset ? (preset.seniors || 0) : 0}"></div>
          <div><label class="lbl">Infants</label><input type="number" min="0" class="inp room-n" name="rooms[${i}][infants]" value="${preset ? (preset.infants || 0) : 0}"></div>
        </div>
        <div class="m room-note" style="font-size:11.5px"></div>`;
      $('roomRows').appendChild(row);
      row.querySelectorAll('select,input').forEach(el => el.addEventListener('input', recalc));
      recalc();
    }

    // fixed = must pick a departure · open = must name a date · both = either
    const DATE_NOTES = {
      fixed: 'This package runs on scheduled departures — choose one.',
      open:  'Open-dated package — choose when you want to travel.',
      both:  'Pick a scheduled departure, or leave it blank and name your own travel date.',
    };
    function applyDateMode(pkg) {
      const mode = pkg ? pkg.date_mode : 'fixed';
      $('departureWrap').style.display = mode === 'open' ? 'none' : '';
      $('travelWrap').style.display = mode === 'fixed' ? 'none' : '';
      $('dateNote').textContent = pkg ? (DATE_NOTES[mode] || '') : '';
      $('package_date_id').required = mode === 'fixed';
      $('travel_date').required = mode === 'open';
      if (mode === 'open') $('package_date_id').value = '';
      if (mode === 'fixed') $('travel_date').value = '';
    }
    function fillPackage() {
      const pkg = PKGS[$('package_id').value];
      const ds = $('package_date_id');
      ds.innerHTML = '<option value="">—</option>';
      if (pkg) {
        pkg.dates.forEach(d => { const o = document.createElement('option'); o.value = d.id; o.textContent = d.label + (d.seats === null ? '' : ' (' + d.seats + ' seats left)'); ds.appendChild(o); });
        if (!pkg.dates.length && pkg.date_mode !== 'open') ds.innerHTML = '<option value="">No departures available</option>';
      }
      applyDateMode(pkg);
      // Rates belong to the package, so a package change invalidates every room line.
      $('roomRows').innerHTML = ''; roomIdx = 0;
      if (pkg) addRoom();
      recalc();
    }

    function recalc() {
      const pkg = PKGS[$('package_id').value];
      let total = 0, pax = 0, html = '';

      document.querySelectorAll('.room-row').forEach(row => {
        const pr = pkg ? pkg.pricings.find(p => p.id == row.querySelector('.room-type').value) : null;
        const get = n => +row.querySelector(`[name$="[${n}]"]`).value || 0;
        const a = get('adults'), c = get('children'), s = get('seniors'), i = get('infants');
        const n = a + c + s + i;
        const line = pr ? a * pr.adult_price + c * pr.child_price + s * pr.senior_price + i * pr.infant_price : 0;

        // Infants share a bed, so they never force an extra room.
        const rooms = pr ? Math.ceil(Math.max(1, n - i) / pr.capacity) : 0;
        row.querySelector('.room-note').textContent = n
          ? `${n} pax · ${rooms} room${rooms > 1 ? 's' : ''} · ${money(line)}`
          : 'No passengers in this room type yet.';

        total += line; pax += n;
        if (n && pr) html += `<div class="sum"><span>${pr.tier_name} × ${n} pax (${rooms} rm)</span><span>${money(line)}</span></div>`;
      });

      $('s-rooms').innerHTML = html;
      $('s-pax').textContent = pax;
      $('s-total').textContent = money(total);
      $('roomWarn').textContent = pax === 0 ? 'Add at least one passenger.' : '';
    }
    // With no customers the inline fields are the only way to name one, so they stay open.
    function toggleNewCustomer(){
      const sel = $('customer_id');
      const on = !sel || !!sel.selectedOptions[0]?.dataset.new;
      $('newCustomer').style.display = on ? '' : 'none';
      $('new_customer_name').required = on;
      $('new_customer_phone').required = on;
    }
    toggleNewCustomer();

    $('package_id').addEventListener('change', fillPackage);

    // On a validation bounce, rebuild exactly what was submitted instead of one blank row.
    if (OLD_ROOMS.length) {
      applyDateMode(PKGS[$('package_id').value]);
      const ds = $('package_date_id'), pkg = PKGS[$('package_id').value];
      if (pkg) {
        ds.innerHTML = '<option value="">—</option>';
        pkg.dates.forEach(d => { const o = document.createElement('option'); o.value = d.id; o.textContent = d.label + (d.seats === null ? '' : ' (' + d.seats + ' seats left)'); ds.appendChild(o); });
      }
      OLD_ROOMS.forEach(r => addRoom(r));
      recalc();
    } else {
      fillPackage();
    }
  </script>
@endsection
