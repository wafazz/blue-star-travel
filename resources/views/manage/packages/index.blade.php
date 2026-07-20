@extends('layouts.admin')
@section('title', 'Packages')
@section('console', 'Management')
@section('heading', 'Travel Packages')

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search title / code…" style="min-width:200px">
      <select name="category" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach (\App\Models\Package::CATEGORIES as $k => $label)
          <option value="{{ $k }}" @selected(request('category') === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Any status</option>
        @foreach (\App\Models\Package::STATUSES as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="{{ route('manage.packages.create') }}" class="btn btn-brand btn-sm">＋ New Package</a>
  </div>

  <div class="row g-3">
    @forelse ($packages as $package)
      <div class="col-md-6 col-xl-4">
        <div class="card h-100 overflow-hidden">
          <div class="position-relative" style="height:130px;background:linear-gradient(135deg,#1466ff,#0b3fd1)">
            @if ($package->cover_image)
              <img src="{{ asset('storage/' . $package->cover_image) }}" class="w-100 h-100" style="object-fit:cover" alt="">
            @else
              <div class="d-flex align-items-center justify-content-center h-100 text-white fs-1">🗺️</div>
            @endif
            <span class="badge text-bg-{{ $package->status === 'active' ? 'success' : ($package->status === 'draft' ? 'secondary' : 'warning') }} position-absolute top-0 end-0 m-2">{{ ucfirst($package->status) }}</span>
            @if ($package->featured)<span class="badge text-bg-warning position-absolute top-0 start-0 m-2">⭐ Featured</span>@endif
          </div>
          <div class="p-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">{{ $package->title }}</div>
                <div class="small text-secondary">{{ $package->code }} · {{ $package->categoryLabel() }}</div>
              </div>
            </div>
            <div class="small text-secondary mt-2">📍 {{ $package->destination ?: '—' }} · {{ $package->duration_days }}D{{ $package->duration_nights }}N</div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div><span class="text-secondary small">from</span> <span class="fw-bold text-primary">RM {{ number_format($package->fromPrice()) }}</span></div>
              <span class="small text-secondary">{{ $package->dates_count }} dates</span>
            </div>
            <div class="d-flex gap-2 mt-3">
              <a href="{{ route('manage.packages.show', $package) }}" class="btn btn-sm btn-outline-secondary flex-fill">View</a>
              <a href="{{ route('manage.packages.edit', $package) }}" class="btn btn-sm btn-outline-primary flex-fill">Edit</a>
              <form action="{{ route('manage.packages.destroy', $package) }}" method="POST" onsubmit="return confirm('Delete this package?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">🗑</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12"><div class="card p-5 text-center text-secondary">No packages yet. Create your first package.</div></div>
    @endforelse
  </div>

  <div class="mt-3">{{ $packages->links() }}</div>
@endsection
