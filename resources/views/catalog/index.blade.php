@extends('layouts.customer')
@section('title', 'Packages')

@section('content')
  <div class="abar">
    <div><div class="t">Explore Packages</div><div class="sub">{{ $packages->total() }} trips available</div></div>
  </div>

  <div class="wrap">
    <form method="GET">
      <input type="text" name="q" value="{{ request('q') }}" class="inp" placeholder="🔍 Search destination or package" style="margin-bottom:0">
      @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
    </form>
  </div>

  <div class="seg">
    <a href="{{ route('catalog.index', ['q' => request('q')]) }}" class="{{ ! request('category') ? 'on' : '' }}">All</a>
    @foreach (\App\Models\Package::CATEGORIES as $k => $label)
      <a href="{{ route('catalog.index', ['category' => $k, 'q' => request('q')]) }}" class="{{ request('category') === $k ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="wrap">
    @forelse ($packages as $package)
      <a class="pk" href="{{ route('catalog.show', $package->slug) }}">
        <div class="img" @if ($package->cover_image) style="background-image:url('{{ asset('storage/' . $package->cover_image) }}')" @endif>
          @unless ($package->cover_image) ✈️ @endunless
        </div>
        <div class="bd">
          <div class="cat">{{ $package->categoryLabel() }}</div>
          <div class="n">{{ $package->title }}</div>
          <div class="m">📍 {{ $package->destination }} · {{ $package->duration_days }}D{{ $package->duration_nights }}N</div>
          <div class="pr">RM {{ number_format($package->fromPrice(), 0) }} <small>/ person</small></div>
        </div>
      </a>
    @empty
      <div class="empty">No packages match your search.</div>
    @endforelse
    <div style="padding:10px 0">{{ $packages->links() }}</div>
  </div>
@endsection
