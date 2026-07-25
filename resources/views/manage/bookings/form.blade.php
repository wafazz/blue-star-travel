@extends('layouts.admin')
@section('title', 'New Booking')
@section('console', 'Management')
@section('heading', 'Create Booking')

@php
  $pkgJson = $packages->map(fn ($p) => [
    'id'       => $p->id,
    'title'    => $p->title,
    'pricings' => $p->pricings->map(fn ($pr) => [
      'id'          => $pr->id,
      'tier_name'   => $pr->tier_name,
      'adult_price' => (float) ($pr->promo_price ?? $pr->adult_price),
      'child_price' => (float) $pr->child_price,
      'senior_price'=> (float) ($pr->senior_price ?: ($pr->promo_price ?? $pr->adult_price)),
      'infant_price'=> (float) $pr->infant_price,
      'is_default'  => (bool) $pr->is_default,
    ])->values(),
    'date_mode' => $p->date_mode,
    'dates' => $p->bookableDates()->map(fn ($d) => [
      'id'    => $d->id,
      'label' => $d->depart_date?->format('d M Y') . ($d->return_date ? ' → ' . $d->return_date->format('d M Y') : ''),
      'depart'=> $d->depart_date?->format('Y-m-d'),
      'seats' => $d->seats_total > 0 ? $d->seatsAvailable() : null,
    ])->values(),
  ])->keyBy('id');
@endphp

@section('content')
  <form method="POST" action="{{ route('manage.bookings.store') }}" id="bookingForm" class="row g-3">
    @csrf
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Package & Schedule</h6>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label small fw-semibold">Package</label>
            <select name="package_id" id="package_id" class="form-select" required>
              <option value="">Select package…</option>
              @foreach ($packages as $p)
                <option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->title }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Pricing Tier</label>
            <select name="package_pricing_id" id="package_pricing_id" class="form-select"></select>
          </div>
          <div class="col-md-6" id="departureWrap">
            <label class="form-label small fw-semibold">Departure Date</label>
            <select name="package_date_id" id="package_date_id" class="form-select"><option value="">—</option></select>
            <div class="form-text small" id="dateNote"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Booking Type</label>
            <select name="type" class="form-select">
              @foreach (\App\Models\Booking::TYPES as $k => $label)
                <option value="{{ $k }}" @selected(old('type', 'manual') === $k)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6" id="travelWrap">
            <label class="form-label small fw-semibold">Travel Date</label>
            <input type="date" name="travel_date" value="{{ old('travel_date') }}" class="form-control" id="travel_date">
            <div class="form-text small">Set by the departure when one is chosen.</div>
          </div>
        </div>
      </div>

      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Customer & Agent</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Customer</label>
            <select name="customer_id" class="form-select" required>
              <option value="">Select customer…</option>
              @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }} @if($c->phone)· {{ $c->phone }}@endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Agent (optional)</label>
            <select name="agent_id" class="form-select">
              <option value="">Direct / House</option>
              @foreach ($agents as $a)
                <option value="{{ $a->id }}" @selected(old('agent_id') == $a->id)>{{ $a->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="card p-3 p-lg-4 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Passengers</h6>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPax()">＋ Add pax detail</button>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-3"><label class="form-label small fw-semibold">Adults</label><input type="number" name="adults" id="adults" value="{{ old('adults', 1) }}" min="1" class="form-control"></div>
          <div class="col-3"><label class="form-label small fw-semibold">Children</label><input type="number" name="children" id="children" value="{{ old('children', 0) }}" min="0" class="form-control"></div>
          <div class="col-3"><label class="form-label small fw-semibold">Seniors</label><input type="number" name="seniors" id="seniors" value="{{ old('seniors', 0) }}" min="0" class="form-control"></div>
          <div class="col-3"><label class="form-label small fw-semibold">Infants</label><input type="number" name="infants" id="infants" value="{{ old('infants', 0) }}" min="0" class="form-control"></div>
        </div>
        <div id="paxRows"></div>
      </div>

      <div class="card p-3 p-lg-4">
        <label class="form-label small fw-semibold">Notes</label>
        <textarea name="notes" rows="2" class="form-control" placeholder="Special requests, remarks…">{{ old('notes') }}</textarea>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4 position-sticky" style="top:1rem">
        <h6 class="fw-bold mb-3">Price Summary</h6>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Adults × <span id="s-adults">1</span></span><span id="s-adult-line">RM 0.00</span></div>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Children × <span id="s-children">0</span></span><span id="s-child-line">RM 0.00</span></div>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Seniors × <span id="s-seniors">0</span></span><span id="s-senior-line">RM 0.00</span></div>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Infants × <span id="s-infants">0</span></span><span id="s-infant-line">RM 0.00</span></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between mb-2"><span class="text-secondary small">Subtotal</span><span class="fw-semibold" id="s-subtotal">RM 0.00</span></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-secondary small">Discount (RM)</span>
          <input type="number" name="discount" id="discount" value="{{ old('discount', 0) }}" min="0" step="0.01" class="form-control form-control-sm text-end" style="width:110px">
        </div>
        <div class="mb-2">
          <label class="text-secondary small d-block mb-1">Coupon code (optional)</label>
          <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="form-control form-control-sm text-uppercase" placeholder="e.g. RAYA2026">
          @error('coupon_code')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fs-5 fw-bold text-primary"><span>Total</span><span id="s-total">RM 0.00</span></div>
        <button class="btn btn-brand w-100 mt-3">Create Booking</button>
        <a href="{{ route('manage.bookings.index') }}" class="btn btn-link w-100 text-secondary">Cancel</a>
      </div>
    </div>
  </form>

  <script>
    const PKGS = @json($pkgJson);
    const $ = id => document.getElementById(id);
    const money = n => 'RM ' + (Number(n) || 0).toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    let paxIdx = 0;

    function currentPricing() {
      const pkg = PKGS[$('package_id').value];
      if (!pkg) return null;
      return pkg.pricings.find(p => p.id == $('package_pricing_id').value) || pkg.pricings[0] || null;
    }

    function fillPackage() {
      const pkg = PKGS[$('package_id').value];
      const ps = $('package_pricing_id'), ds = $('package_date_id');
      ps.innerHTML = ''; ds.innerHTML = '<option value="">—</option>';
      if (pkg) {
        pkg.pricings.forEach(p => {
          const o = document.createElement('option');
          o.value = p.id; o.textContent = p.tier_name + ' — ' + money(p.adult_price);
          if (p.is_default) o.selected = true;
          ps.appendChild(o);
        });
        pkg.dates.forEach(d => {
          const o = document.createElement('option');
          o.value = d.id; o.textContent = d.label + (d.seats === null ? '' : ' (' + d.seats + ' seats left)');
          o.dataset.depart = d.depart || '';
          ds.appendChild(o);
        });
        if (!pkg.dates.length && pkg.date_mode !== 'open') ds.innerHTML = '<option value="">No departures available</option>';
      }
      applyDateMode(pkg);
      recalc();
    }

    // fixed = must pick a departure · open = must name a date · both = either
    const DATE_NOTES = {
      fixed: 'Scheduled departures only — one must be chosen.',
      open:  'Open-dated package — set the travel date instead.',
      both:  'Pick a departure, or leave blank and set a travel date.',
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

    function recalc() {
      const pr = currentPricing();
      const a = +$('adults').value || 0, c = +$('children').value || 0, s = +$('seniors').value || 0, i = +$('infants').value || 0;
      const ap = pr ? pr.adult_price : 0, cp = pr ? pr.child_price : 0, sp = pr ? pr.senior_price : 0, ip = pr ? pr.infant_price : 0;
      const aLine = a * ap, cLine = c * cp, sLine = s * sp, iLine = i * ip;
      const sub = aLine + cLine + sLine + iLine;
      const disc = +$('discount').value || 0;
      $('s-adults').textContent = a; $('s-children').textContent = c; $('s-seniors').textContent = s; $('s-infants').textContent = i;
      $('s-adult-line').textContent = money(aLine);
      $('s-child-line').textContent = money(cLine);
      $('s-senior-line').textContent = money(sLine);
      $('s-infant-line').textContent = money(iLine);
      $('s-subtotal').textContent = money(sub);
      $('s-total').textContent = money(Math.max(0, sub - disc));
    }

    function addPax() {
      const wrap = document.getElementById('paxRows');
      const row = document.createElement('div');
      row.className = 'row g-2 mb-2 align-items-center';
      row.innerHTML = `
        <div class="col-4"><input name="pax[${paxIdx}][name]" class="form-control form-control-sm" placeholder="Full name"></div>
        <div class="col-3"><select name="pax[${paxIdx}][type]" class="form-select form-select-sm">
          <option value="adult">Adult</option><option value="child">Child</option><option value="senior">Senior</option><option value="infant">Infant</option></select></div>
        <div class="col-3"><input name="pax[${paxIdx}][ic_passport_no]" class="form-control form-control-sm" placeholder="IC / Passport"></div>
        <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.row').remove()">✕</button></div>`;
      wrap.appendChild(row);
      paxIdx++;
    }

    ['package_id'].forEach(id => $(id).addEventListener('change', fillPackage));
    ['package_pricing_id','adults','children','seniors','infants','discount'].forEach(id => $(id).addEventListener('input', recalc));
    $('package_date_id').addEventListener('change', function () {
      const dep = this.selectedOptions[0]?.dataset.depart;
      if (dep && !$('travel_date').value) $('travel_date').value = dep;
    });
    fillPackage();
  </script>
@endsection
