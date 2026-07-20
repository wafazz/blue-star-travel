@extends('layouts.agent')
@section('title', 'New Booking')

@php
  $pkgJson = $packages->map(fn ($p) => [
    'id' => $p->id,
    'pricings' => $p->pricings->map(fn ($pr) => [
      'id' => $pr->id, 'tier_name' => $pr->tier_name,
      'adult_price' => (float) ($pr->promo_price ?? $pr->adult_price),
      'child_price' => (float) $pr->child_price, 'infant_price' => (float) $pr->infant_price,
      'is_default' => (bool) $pr->is_default,
    ])->values(),
    'dates' => $p->dates->map(fn ($d) => [
      'id' => $d->id,
      'label' => $d->depart_date?->format('d M Y') . ($d->return_date ? ' → ' . $d->return_date->format('d M Y') : ''),
      'depart' => $d->depart_date?->format('Y-m-d'), 'seats' => $d->seatsAvailable(),
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
      <label class="lbl">Pricing tier</label>
      <select name="package_pricing_id" id="package_pricing_id" class="inp"></select>
      <label class="lbl">Departure date</label>
      <select name="package_date_id" id="package_date_id" class="inp"><option value="">—</option></select>
      <input type="hidden" name="type" value="online">
    </div>

    <div class="card">
      <h3>Customer</h3>
      <label class="lbl">Select your customer</label>
      <select name="customer_id" class="inp" required>
        <option value="">Choose…</option>
        @foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach
      </select>
      @if ($customers->isEmpty())<div class="m" style="color:var(--danger);font-size:12px">No customers assigned to you yet.</div>@endif
    </div>

    <div class="card">
      <h3>Passengers</h3>
      <div class="row2">
        <div><label class="lbl">Adults</label><input type="number" name="adults" id="adults" value="{{ old('adults', 1) }}" min="1" class="inp"></div>
        <div><label class="lbl">Children</label><input type="number" name="children" id="children" value="{{ old('children', 0) }}" min="0" class="inp"></div>
        <div><label class="lbl">Infants</label><input type="number" name="infants" id="infants" value="{{ old('infants', 0) }}" min="0" class="inp"></div>
      </div>
      <label class="lbl">Coupon code (optional)</label>
      <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="inp" style="text-transform:uppercase" placeholder="e.g. RAYA2026">
      <label class="lbl">Notes (optional)</label>
      <textarea name="notes" rows="2" class="inp" placeholder="Special requests…">{{ old('notes') }}</textarea>
    </div>

    <div class="card">
      <h3>Summary</h3>
      <div class="sum"><span>Adults × <span id="s-adults">1</span></span><span id="s-adult-line">RM 0</span></div>
      <div class="sum"><span>Children × <span id="s-children">0</span></span><span id="s-child-line">RM 0</span></div>
      <div class="sum"><span>Infants × <span id="s-infants">0</span></span><span id="s-infant-line">RM 0</span></div>
      <div class="sum total"><span>Total</span><span id="s-total">RM 0</span></div>
    </div>

    <button class="btn" style="margin-bottom:20px">Submit Booking</button>
  </form>

  <script>
    const PKGS = @json($pkgJson);
    const $ = id => document.getElementById(id);
    const money = n => 'RM ' + (Number(n) || 0).toLocaleString('en-MY', {maximumFractionDigits: 0});
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
        pkg.pricings.forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.tier_name + ' — ' + money(p.adult_price); if (p.is_default) o.selected = true; ps.appendChild(o); });
        pkg.dates.forEach(d => { const o = document.createElement('option'); o.value = d.id; o.textContent = d.label + (d.seats ? ' (' + d.seats + ' seats)' : ' (full)'); ds.appendChild(o); });
      }
      recalc();
    }
    function recalc() {
      const pr = currentPricing();
      const a = +$('adults').value || 0, c = +$('children').value || 0, i = +$('infants').value || 0;
      const ap = pr ? pr.adult_price : 0, cp = pr ? pr.child_price : 0, ip = pr ? pr.infant_price : 0;
      $('s-adults').textContent = a; $('s-children').textContent = c; $('s-infants').textContent = i;
      $('s-adult-line').textContent = money(a * ap); $('s-child-line').textContent = money(c * cp); $('s-infant-line').textContent = money(i * ip);
      $('s-total').textContent = money(a * ap + c * cp + i * ip);
    }
    $('package_id').addEventListener('change', fillPackage);
    ['package_pricing_id','adults','children','infants'].forEach(id => $(id).addEventListener('input', recalc));
    fillPackage();
  </script>
@endsection
