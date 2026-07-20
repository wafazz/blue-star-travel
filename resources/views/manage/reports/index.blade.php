@extends('layouts.admin')
@section('title', 'Reports')
@section('console', 'Management')
@section('heading', 'Reports & Analytics')

@section('content')
  <div class="card p-3 p-lg-4 mb-3">
    <h6 class="fw-bold mb-1">Decision-ready data</h6>
    <p class="text-secondary small mb-0">Pick a report, set the date range, then export to PDF, Excel or CSV.</p>
  </div>

  <div class="row g-3">
    @foreach ($reports as $key => $r)
      <div class="col-md-6 col-xl-4">
        <a href="{{ route('manage.reports.show', $key) }}" class="text-decoration-none">
          <div class="card h-100 p-3 p-lg-4">
            <span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-primary bg-opacity-10" style="width:40px;height:40px">{{ $r['icon'] }}</span>
            <div class="fw-bold mt-3 text-dark">{{ $r['title'] }}</div>
            <div class="text-secondary small mt-1">{{ $r['desc'] }}</div>
            <div class="mt-3 small fw-semibold text-primary">Open report →</div>
          </div>
        </a>
      </div>
    @endforeach
  </div>
@endsection
