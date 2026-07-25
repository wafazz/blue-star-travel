@php $rt = data_get($c, 'rate_type', 'percent'); $unit = $rt === 'fixed' ? 'RM' : '%'; @endphp
<div class="border rounded-3 p-3 position-relative commission-row" data-rate="{{ $rt }}">
  <button type="button" class="btn-close position-absolute top-0 end-0 m-2" onclick="removeRow(this, '.commission-row')"></button>
  <div class="row g-2 align-items-end">
    <div class="col-4 col-md-2">
      <label class="form-label small fw-semibold mb-1">Level</label>
      <input type="number" min="1" name="commissions[{{ $i }}][level]" value="{{ data_get($c, 'level', (int) $i + 1) }}" class="form-control form-control-sm" placeholder="1">
    </div>
    <div class="col-8 col-md-2">
      <label class="form-label small fw-semibold mb-1">Payout Type</label>
      <select name="commissions[{{ $i }}][rate_type]" class="form-select form-select-sm rate-toggle" onchange="onRateChange(this)">
        <option value="percent" @selected($rt === 'percent')>Percentage (%)</option>
        <option value="fixed" @selected($rt === 'fixed')>Fixed (RM)</option>
      </select>
    </div>
    <div class="col-3 col-md-2">
      <label class="form-label small mb-1">Adult</label>
      <div class="input-group input-group-sm"><span class="input-group-text unit-adorn">{{ $unit }}</span><input type="number" step="0.01" min="0" name="commissions[{{ $i }}][adult_value]" value="{{ data_get($c, 'adult_value') }}" class="form-control"></div>
    </div>
    <div class="col-3 col-md-2">
      <label class="form-label small mb-1">Child</label>
      <div class="input-group input-group-sm"><span class="input-group-text unit-adorn">{{ $unit }}</span><input type="number" step="0.01" min="0" name="commissions[{{ $i }}][child_value]" value="{{ data_get($c, 'child_value') }}" class="form-control"></div>
    </div>
    <div class="col-3 col-md-2">
      <label class="form-label small mb-1">Senior</label>
      <div class="input-group input-group-sm"><span class="input-group-text unit-adorn">{{ $unit }}</span><input type="number" step="0.01" min="0" name="commissions[{{ $i }}][senior_value]" value="{{ data_get($c, 'senior_value') }}" class="form-control"></div>
    </div>
    <div class="col-3 col-md-2">
      <label class="form-label small mb-1">Infant</label>
      <div class="input-group input-group-sm"><span class="input-group-text unit-adorn">{{ $unit }}</span><input type="number" step="0.01" min="0" name="commissions[{{ $i }}][infant_value]" value="{{ data_get($c, 'infant_value') }}" class="form-control"></div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-3 mt-2">
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" name="commissions[{{ $i }}][active]" value="1" @checked(data_get($c, 'active', true))>
      <label class="form-check-label small text-secondary">Active</label>
    </div>
    <div class="form-check form-switch mb-0">
      <input class="form-check-input hq-toggle" type="checkbox" name="commissions[{{ $i }}][is_hq]" value="1" @checked(data_get($c, 'is_hq', false)) onchange="onHqChange(this)">
      <label class="form-check-label small text-secondary">🏢 HQ level (company earns this — ignores the upline)</label>
    </div>
  </div>
</div>
