@extends('layouts.customer')
@section('title', $package->title)

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('catalog.index') }}">‹</a>
    <div><div class="t">{{ \Illuminate\Support\Str::limit($package->title, 26) }}</div><div class="sub">{{ $package->categoryLabel() }}</div></div>
  </div>

  <div class="wrap">
    <div class="pk" style="margin-bottom:14px">
      <div class="img" style="height:170px @if ($package->cover_image); background-image:url('{{ asset('storage/' . $package->cover_image) }}') @endif">
        @unless ($package->cover_image) ✈️ @endunless
      </div>
      <div class="bd">
        <div class="n" style="font-size:16px">{{ $package->title }}</div>
        <div class="m">📍 {{ $package->destination }} · 🗓️ {{ $package->duration_days }} Days {{ $package->duration_nights }} Nights</div>
        <div class="pr">RM {{ number_format($package->fromPrice(), 0) }} <small>/ person</small></div>
      </div>
    </div>

    @if ($package->summary)
      <div class="card"><h3>Overview</h3><div style="font-size:13px;line-height:1.6;color:#3a4668">{{ $package->summary }}</div></div>
    @endif

    <div class="card">
      <h3>Pricing</h3>
      @foreach ($package->pricings as $p)
        <div class="sum"><span style="color:var(--muted)">{{ $p->tier_name }} — Adult</span><span style="font-weight:700">RM {{ number_format($p->promo_price ?? $p->adult_price, 2) }}</span></div>
        <div class="sum"><span style="color:var(--muted)">{{ $p->tier_name }} — Child</span><span style="font-weight:700">RM {{ number_format($p->child_price, 2) }}</span></div>
        <div class="sum"><span style="color:var(--muted)">{{ $p->tier_name }} — Infant</span><span style="font-weight:700">RM {{ number_format($p->infant_price, 2) }}</span></div>
      @endforeach
    </div>

    <div class="card">
      <h3>Available Departures</h3>
      @forelse ($dates as $d)
        <div class="sum">
          <span style="color:var(--muted)">{{ $d->depart_date->format('d M Y') }} → {{ optional($d->return_date)->format('d M Y') }}</span>
          <span class="badge b-success">{{ $d->seats_total - $d->seats_booked }} seats</span>
        </div>
      @empty
        <div style="font-size:13px;color:var(--muted)">No fixed departures — contact us for custom dates.</div>
      @endforelse
    </div>

    @if ($package->itinerary)
      <div class="card"><h3>Itinerary</h3><div style="font-size:13px;line-height:1.6;color:#3a4668;white-space:pre-line">{{ $package->itinerary }}</div></div>
    @endif

    @if ($package->inclusions)
      <div class="card"><h3>What's Included</h3><div style="font-size:13px;line-height:1.6;color:#3a4668;white-space:pre-line">{{ $package->inclusions }}</div></div>
    @endif

    @if ($package->terms)
      <div class="card"><h3>Terms &amp; Conditions</h3><div style="font-size:12px;line-height:1.6;color:var(--muted);white-space:pre-line">{{ $package->terms }}</div></div>
    @endif

    <div style="padding-bottom:20px">
      @auth
        @if (auth()->user()->hasRole('customer'))
          <a class="btn" href="{{ route('customer.bookings.create', ['package' => $package->slug]) }}">🧳 Book This Trip</a>
        @endif
      @else
        <a class="btn" href="{{ route('login') }}">🔑 Sign in to book</a>
        <div style="text-align:center;font-size:12px;color:var(--muted);margin-top:12px">
          New here? <a href="{{ route('register') }}" style="color:var(--blue);font-weight:700">Create an account</a>
        </div>
      @endauth
    </div>
  </div>
@endsection
