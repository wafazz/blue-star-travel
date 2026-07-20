@extends('layouts.customer')
@section('title', 'My Account')

@section('content')
  @php $u = auth()->user(); @endphp

  <div class="hero">
    <div class="top">
      <div class="av">{{ $u->initials() }}</div>
      <div><h1>Hi, {{ strtok($u->name, ' ') }} 👋</h1><p>Welcome to your Blue Travel account</p></div>
    </div>
  </div>

  <div class="card pull">
    <div style="font-size:12px;color:var(--muted);font-weight:700">🎁 Loyalty Points</div>
    <div style="font-size:30px;font-weight:800;color:var(--blue)">{{ number_format($customer?->loyalty_points ?? 0) }}</div>
    <div style="font-size:12px;color:var(--muted)">Earn points on every booking</div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  @if ($banner)
    <div class="sec">
      <div class="pk" style="margin-bottom:0">
        <div class="img" style="height:110px @if ($banner->image); background-image:url('{{ asset('storage/' . $banner->image) }}') @endif">
          @unless ($banner->image) 🎉 @endunless
        </div>
        <div class="bd"><div class="n">{{ $banner->title }}</div><div class="m">{{ $banner->subtitle }}</div></div>
      </div>
    </div>
  @endif

  <div class="sec">
    <h3>Explore</h3>
    <div class="qa">
      <a class="q" href="{{ route('catalog.index') }}"><div class="ic">🗺️</div><div class="t">Packages</div></a>
      <a class="q" href="{{ route('catalog.index', ['category' => 'umrah']) }}"><div class="ic">🕌</div><div class="t">Umrah</div></a>
      <a class="q" href="{{ route('customer.bookings.index') }}"><div class="ic">📋</div><div class="t">My Trips</div></a>
      <a class="q" href="{{ route('customer.tickets.index') }}"><div class="ic">🎧</div><div class="t">Support</div></a>
    </div>
  </div>

  <div class="sec">
    <h3>My Travel</h3>
    <div class="card" style="margin-bottom:0">
      <div class="sum"><span style="color:var(--muted)">Total trips</span><span style="font-weight:800">{{ $stats['trips'] }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Upcoming</span><span style="font-weight:800;color:var(--blue)">{{ $stats['upcoming'] }}</span></div>
      <div class="sum"><span style="color:var(--muted)">Total paid</span><span style="font-weight:800;color:var(--ok)">RM {{ number_format($stats['spend'], 2) }}</span></div>
      <div class="sum total" style="font-size:15px"><span>Outstanding</span><span style="color:{{ $stats['outstanding'] > 0 ? 'var(--danger)' : 'var(--ok)' }}">RM {{ number_format($stats['outstanding'], 2) }}</span></div>
    </div>
  </div>

  <div class="sec">
    <h3>Recent Bookings</h3>
    @forelse ($recent as $booking)
      <a class="brow" href="{{ route('customer.bookings.show', $booking) }}">
        <div>
          <div class="n">{{ $booking->package?->title ?? '—' }}</div>
          <div class="m">{{ $booking->booking_no }} · RM {{ number_format($booking->total_amount, 0) }}</div>
        </div>
        <span class="badge b-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
      </a>
    @empty
      <div class="card" style="text-align:center">
        <div style="font-size:13px;color:var(--muted);font-weight:600;margin-bottom:12px">No trips yet — start exploring ✈️</div>
        <a class="btn" href="{{ route('catalog.index') }}">Browse Packages</a>
      </div>
    @endforelse
  </div>

  @if ($featured->isNotEmpty())
    <div class="sec">
      <h3>Recommended For You</h3>
      @foreach ($featured as $package)
        <a class="brow" href="{{ route('catalog.show', $package->slug) }}">
          <div>
            <div class="n">{{ $package->title }}</div>
            <div class="m">📍 {{ $package->destination }} · {{ $package->duration_days }}D{{ $package->duration_nights }}N</div>
          </div>
          <span class="badge b-info">RM {{ number_format($package->fromPrice(), 0) }}</span>
        </a>
      @endforeach
    </div>
  @endif

  <div class="sec">
    <h3>My Account</h3>
    <a class="lrow" href="{{ route('customer.bookings.index') }}"><div class="ic">📋</div><div class="tx"><b>My Bookings</b><span>Track status &amp; documents</span></div><span>›</span></a>
    <a class="lrow" href="{{ route('customer.profile.edit') }}"><div class="ic">🛂</div><div class="tx"><b>Passport &amp; Profile</b><span>Manage travel details</span></div><span>›</span></a>
    <a class="lrow" href="{{ route('customer.tickets.index') }}"><div class="ic">🎧</div><div class="tx"><b>Support</b><span>Ask a question or complaint</span></div><span>›</span></a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn-out">
        <div class="lrow logout"><div class="ic">🚪</div><div class="tx"><b>Log Out</b><span>Sign out of your account</span></div><span>›</span></div>
      </button>
    </form>
  </div>

  <div style="text-align:center;color:#5c6a90;font-size:12px;padding:14px 0 30px;font-weight:600">Blue Travel · Customer Portal v1.0</div>
@endsection
