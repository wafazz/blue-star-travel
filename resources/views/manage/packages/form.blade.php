@extends('layouts.admin')
@section('title', $package->exists ? 'Edit Package' : 'New Package')
@section('console', 'Management')
@section('heading', $package->exists ? 'Edit Package' : 'New Package')

@php
  $pricings = old('pricings', $package->exists ? $package->pricings->toArray() : []);
  if (empty($pricings)) { $pricings = [['tier_name' => 'Standard', 'is_default' => true]]; }
  $defaultPricing = old('default_pricing', collect($pricings)->search(fn ($p) => ! empty($p['is_default'])) ?: 0);
  $dates = old('dates', $package->exists ? $package->dates->toArray() : []);
@endphp

@section('content')
  <form method="POST" action="{{ $package->exists ? route('manage.packages.update', $package) : route('manage.packages.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($package->exists) @method('PUT') @endif

    <div class="row g-3">
      <!-- Main details -->
      <div class="col-lg-8">
        <div class="card p-4 mb-3">
          <h6 class="fw-bold mb-3">Package Details</h6>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold small">Title *</label>
              <input type="text" name="title" value="{{ old('title', $package->title) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Category *</label>
              <select name="category" class="form-select" required>
                @foreach (\App\Models\Package::CATEGORIES as $k => $label)
                  <option value="{{ $k }}" @selected(old('category', $package->category) === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Destination</label>
              <input type="text" name="destination" value="{{ old('destination', $package->destination) }}" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Days *</label>
              <input type="number" name="duration_days" value="{{ old('duration_days', $package->duration_days ?? 1) }}" class="form-control" min="1" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Nights *</label>
              <input type="number" name="duration_nights" value="{{ old('duration_nights', $package->duration_nights ?? 0) }}" class="form-control" min="0" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Summary</label>
              <textarea name="summary" rows="2" class="form-control" maxlength="500">{{ old('summary', $package->summary) }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Description</label>
              <textarea name="description" rows="3" class="form-control">{{ old('description', $package->description) }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Itinerary <span class="text-secondary fw-normal">(one line per day)</span></label>
              <textarea name="itinerary" rows="4" class="form-control">{{ old('itinerary', $package->itinerary) }}</textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Inclusions</label>
              <textarea name="inclusions" rows="3" class="form-control">{{ old('inclusions', $package->inclusions) }}</textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Exclusions</label>
              <textarea name="exclusions" rows="3" class="form-control">{{ old('exclusions', $package->exclusions) }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Terms &amp; Conditions</label>
              <textarea name="terms" rows="2" class="form-control">{{ old('terms', $package->terms) }}</textarea>
            </div>
          </div>
        </div>

        <!-- Pricing tiers -->
        <div class="card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Pricing Tiers</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPricing()">＋ Add Tier</button>
          </div>
          <div class="small text-secondary mb-2">Select the radio to mark the default tier (used for the “from” price).</div>
          <div id="pricingRows" class="d-flex flex-column gap-3">
            @foreach ($pricings as $i => $p)
              @include('manage.packages.partials.pricing-row', ['i' => $i, 'p' => $p, 'defaultPricing' => $defaultPricing])
            @endforeach
          </div>
        </div>

        <!-- Travel dates -->
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Travel Dates &amp; Seats</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addDate()">＋ Add Date</button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="text-secondary small"><tr><th>Depart</th><th>Return</th><th>Seats Total</th><th>Booked</th><th>Status</th><th></th></tr></thead>
              <tbody id="dateRows">
                @foreach ($dates as $i => $d)
                  @include('manage.packages.partials.date-row', ['i' => $i, 'd' => $d])
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div class="card p-4 mb-3">
          <h6 class="fw-bold mb-3">Publishing</h6>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Status *</label>
            <select name="status" class="form-select" required>
              @foreach (\App\Models\Package::STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(old('status', $package->status ?? 'draft') === $k)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Provider</label>
            <select name="provider_id" class="form-select">
              <option value="">— None —</option>
              @foreach ($providers as $prov)
                <option value="{{ $prov->id }}" @selected((string) old('provider_id', $package->provider_id) === (string) $prov->id)>{{ $prov->name }} ({{ $prov->typeLabel() }})</option>
              @endforeach
            </select>
          </div>
          <div class="form-check">
            <input type="checkbox" name="featured" value="1" id="featured" class="form-check-input" @checked(old('featured', $package->featured))>
            <label class="form-check-label small" for="featured">⭐ Featured package</label>
          </div>
        </div>

        <div class="card p-4">
          <h6 class="fw-bold mb-3">Images</h6>
          <label class="form-label fw-semibold small">Cover Image</label>
          @if ($package->cover_image)
            <img src="{{ asset('storage/' . $package->cover_image) }}" class="img-fluid rounded mb-2" style="max-height:120px" alt="">
          @endif
          <input type="file" name="cover_image" accept="image/*" class="form-control form-control-sm mb-3">

          <label class="form-label fw-semibold small">Gallery (add more)</label>
          <input type="file" name="gallery[]" accept="image/*" multiple class="form-control form-control-sm">
          @if ($package->exists && ! empty($package->gallery))
            <div class="d-flex flex-wrap gap-2 mt-2">
              @foreach ($package->gallery as $img)
                <img src="{{ asset('storage/' . $img) }}" class="rounded" style="width:56px;height:56px;object-fit:cover" alt="">
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-brand">💾 Save Package</button>
      <a href="{{ route('manage.packages.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>

  <template id="pricingTpl">@include('manage.packages.partials.pricing-row', ['i' => '__I__', 'p' => [], 'defaultPricing' => -1])</template>
  <template id="dateTpl">@include('manage.packages.partials.date-row', ['i' => '__I__', 'd' => []])</template>

  <script>
    let pIdx = {{ count($pricings) }};
    let dIdx = {{ count($dates) }};
    function addPricing(){
      const html = document.getElementById('pricingTpl').innerHTML.replaceAll('__I__', pIdx++);
      document.getElementById('pricingRows').insertAdjacentHTML('beforeend', html);
    }
    function addDate(){
      const html = document.getElementById('dateTpl').innerHTML.replaceAll('__I__', dIdx++);
      document.getElementById('dateRows').insertAdjacentHTML('beforeend', html);
    }
    function removeRow(btn, sel){ btn.closest(sel).remove(); }
    @if (empty($dates)) addDate(); @endif
  </script>
@endsection
