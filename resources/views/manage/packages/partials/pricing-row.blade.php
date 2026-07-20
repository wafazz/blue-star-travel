<div class="border rounded-3 p-3 position-relative pricing-row">
  <button type="button" class="btn-close position-absolute top-0 end-0 m-2" onclick="removeRow(this, '.pricing-row')"></button>
  <div class="row g-2">
    <div class="col-md-6">
      <label class="form-label small fw-semibold mb-1">Tier Name</label>
      <input type="text" name="pricings[{{ $i }}][tier_name]" value="{{ data_get($p, 'tier_name') }}" class="form-control form-control-sm" placeholder="Standard / Deluxe / VIP">
    </div>
    <div class="col-md-6 d-flex align-items-end">
      <div class="form-check">
        <input type="radio" name="default_pricing" value="{{ $i }}" class="form-check-input" @checked((string) $defaultPricing === (string) $i)>
        <label class="form-check-label small">Default tier</label>
      </div>
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Adult (RM)</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][adult_price]" value="{{ data_get($p, 'adult_price') }}" class="form-control form-control-sm">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Child</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][child_price]" value="{{ data_get($p, 'child_price') }}" class="form-control form-control-sm">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Infant</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][infant_price]" value="{{ data_get($p, 'infant_price') }}" class="form-control form-control-sm">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Promo</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][promo_price]" value="{{ data_get($p, 'promo_price') }}" class="form-control form-control-sm">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">Early Bird</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][early_bird_price]" value="{{ data_get($p, 'early_bird_price') }}" class="form-control form-control-sm">
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label small mb-1">EB Until</label>
      <input type="date" name="pricings[{{ $i }}][early_bird_until]" value="{{ data_get($p, 'early_bird_until') ? \Illuminate\Support\Str::substr(data_get($p, 'early_bird_until'), 0, 10) : '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">Group Min Pax</label>
      <input type="number" name="pricings[{{ $i }}][group_min]" value="{{ data_get($p, 'group_min') }}" class="form-control form-control-sm">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">Group Disc. %</label>
      <input type="number" step="0.01" name="pricings[{{ $i }}][group_discount_percent]" value="{{ data_get($p, 'group_discount_percent') }}" class="form-control form-control-sm">
    </div>
  </div>
</div>
