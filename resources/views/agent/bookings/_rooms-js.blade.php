{{--
  Shared room-line engine for the agent booking create + edit forms.
  Expects: $packages (collection) and optionally $presetRooms (array of room lines).
  Each page supplies its own bootstrap call at the end — see bootRooms().
--}}
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

<script>
  const PKGS = @json($pkgJson);
  const $ = id => document.getElementById(id);
  const money = n => 'RM ' + (Number(n) || 0).toLocaleString('en-MY', {maximumFractionDigits: 0});
  const PRESET_ROOMS = @json($presetRooms ?? []);
  let roomIdx = 0;

  // − / + counter, as drawn in the client mockup. The input stays a real number field
  // so the value still posts normally and can be typed into on a desktop browser.
  function stepper(i, field, label, value) {
    return `<div>
      <label class="lbl">${label}</label>
      <div style="display:flex;align-items:center;gap:6px">
        <button type="button" class="pax-step" onclick="bump(this,-1)">−</button>
        <input type="number" min="0" class="inp room-n" style="margin:0;text-align:center"
               name="rooms[${i}][${field}]" value="${value}">
        <button type="button" class="pax-step" onclick="bump(this,1)">+</button>
      </div>
    </div>`;
  }

  function bump(btn, delta) {
    const input = btn.parentElement.querySelector('input');
    input.value = Math.max(0, (+input.value || 0) + delta);
    input.dispatchEvent(new Event('input', {bubbles: true}));
  }

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
        ${stepper(i, 'adults', 'Adults', preset ? (preset.adults || 0) : (i === 0 ? 1 : 0))}
        ${stepper(i, 'children', 'Children', preset ? (preset.children || 0) : 0)}
      </div>
      <div class="row2">
        ${stepper(i, 'seniors', 'Seniors', preset ? (preset.seniors || 0) : 0)}
        ${stepper(i, 'infants', 'Infants', preset ? (preset.infants || 0) : 0)}
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

  function fillDepartures(pkg, selectedId) {
    const ds = $('package_date_id');
    ds.innerHTML = '<option value="">—</option>';
    if (!pkg) return;
    pkg.dates.forEach(d => {
      const o = document.createElement('option');
      o.value = d.id;
      o.textContent = d.label + (d.seats === null ? '' : ' (' + d.seats + ' seats left)');
      if (selectedId && d.id == selectedId) o.selected = true;
      ds.appendChild(o);
    });
    if (!pkg.dates.length && pkg.date_mode !== 'open') ds.innerHTML = '<option value="">No departures available</option>';
  }

  function fillPackage() {
    const pkg = PKGS[$('package_id').value];
    fillDepartures(pkg);
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

  // Rebuild exactly what was submitted (validation bounce) or staged (draft edit),
  // instead of resetting to one blank row.
  function bootRooms(selectedDepartureId) {
    if (PRESET_ROOMS.length) {
      const pkg = PKGS[$('package_id').value];
      fillDepartures(pkg, selectedDepartureId);
      applyDateMode(pkg);
      PRESET_ROOMS.forEach(r => addRoom(r));
      recalc();
    } else {
      fillPackage();
    }
  }

  $('package_id').addEventListener('change', fillPackage);
</script>
