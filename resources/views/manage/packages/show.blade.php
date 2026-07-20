@extends('layouts.admin')
@section('title', $package->title)
@section('console', 'Management')
@section('heading', 'Package Detail')

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
    <div>
      <h4 class="fw-bold mb-1">{{ $package->title }}</h4>
      <div class="text-secondary">{{ $package->code }} · {{ $package->categoryLabel() }} · 📍 {{ $package->destination ?: '—' }}</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('manage.packages.edit', $package) }}" class="btn btn-brand btn-sm">✏️ Edit</a>
      <a href="{{ route('manage.packages.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card overflow-hidden mb-3">
        <div style="height:200px;background:linear-gradient(135deg,#1466ff,#0b3fd1)">
          @if ($package->cover_image)
            <img src="{{ asset('storage/' . $package->cover_image) }}" class="w-100 h-100" style="object-fit:cover" alt="">
          @else
            <div class="d-flex align-items-center justify-content-center h-100 text-white display-4">🗺️</div>
          @endif
        </div>
        <div class="p-4">
          <div class="d-flex gap-2 mb-3">
            <span class="badge text-bg-{{ $package->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($package->status) }}</span>
            <span class="badge text-bg-light">{{ $package->duration_days }}D{{ $package->duration_nights }}N</span>
            @if ($package->featured)<span class="badge text-bg-warning">⭐ Featured</span>@endif
            @if ($package->provider)<span class="badge text-bg-light">🤝 {{ $package->provider->name }}</span>@endif
          </div>
          @if ($package->summary)<p class="text-secondary">{{ $package->summary }}</p>@endif
          @if ($package->itinerary)
            <h6 class="fw-bold mt-3">Itinerary</h6>
            <div class="small" style="white-space:pre-line">{{ $package->itinerary }}</div>
          @endif
          <div class="row mt-3">
            @if ($package->inclusions)
              <div class="col-md-6"><h6 class="fw-bold">✅ Inclusions</h6><div class="small text-secondary" style="white-space:pre-line">{{ $package->inclusions }}</div></div>
            @endif
            @if ($package->exclusions)
              <div class="col-md-6"><h6 class="fw-bold">❌ Exclusions</h6><div class="small text-secondary" style="white-space:pre-line">{{ $package->exclusions }}</div></div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 mb-3">
        <h6 class="fw-bold mb-3">💰 Pricing Tiers</h6>
        @forelse ($package->pricings as $pr)
          <div class="border rounded-3 p-2 mb-2 {{ $pr->is_default ? 'border-primary' : '' }}">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold">{{ $pr->tier_name }}</span>
              @if ($pr->is_default)<span class="badge text-bg-primary">Default</span>@endif
            </div>
            <div class="small text-secondary">Adult RM {{ number_format($pr->adult_price) }} · Child RM {{ number_format($pr->child_price) }} · Infant RM {{ number_format($pr->infant_price) }}</div>
            @if ($pr->promo_price)<div class="small text-success">Promo RM {{ number_format($pr->promo_price) }}</div>@endif
          </div>
        @empty
          <div class="text-secondary small">No pricing tiers.</div>
        @endforelse
      </div>

      <div class="card p-3">
        <h6 class="fw-bold mb-3">📅 Travel Dates</h6>
        @forelse ($package->dates as $d)
          <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
            <div>
              <div class="fw-semibold">{{ $d->depart_date->format('d M Y') }}</div>
              <div class="text-secondary">{{ $d->seatsAvailable() }} / {{ $d->seats_total }} seats left</div>
            </div>
            <span class="badge text-bg-{{ $d->status === 'open' ? 'success' : 'secondary' }}">{{ ucfirst($d->status) }}</span>
          </div>
        @empty
          <div class="text-secondary small">No travel dates.</div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
