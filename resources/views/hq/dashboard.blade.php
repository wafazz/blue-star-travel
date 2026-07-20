@extends('layouts.admin')
@section('title', 'HQ Dashboard')
@section('console', 'HQ Management')
@section('heading', 'Executive Dashboard')

@section('content')
  <div class="row g-3 mb-2">
    @foreach ($kpis as [$label, $value, $icon, $color])
      <div class="col-6 col-lg-3">
        <div class="card h-100 p-3">
          <div class="d-flex justify-content-between align-items-center">
            <span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-{{ $color }} bg-opacity-10" style="width:40px;height:40px">{{ $icon }}</span>
          </div>
          <div class="fs-4 fw-bold mt-3">{{ $value }}</div>
          <div class="text-secondary small">{{ $label }}</div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4 h-100">
        <h6 class="fw-bold mb-4">📈 Revenue — last 6 months</h6>
        <div class="d-flex align-items-end justify-content-between gap-2" style="height:200px">
          @foreach ($trend as $t)
            <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height:100%">
              <div class="small fw-semibold mb-1" style="font-size:.7rem">{{ $t['value'] > 0 ? 'RM ' . number_format($t['value'] / 1000, 1) . 'k' : '' }}</div>
              <div class="w-100 rounded-top" style="background:linear-gradient(180deg,#1466ff,#0b3fd1);height:{{ max(2, round($t['value'] / $trendMax * 100)) }}%;min-height:2px"></div>
              <div class="text-secondary small mt-2">{{ $t['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4 h-100">
        <h6 class="fw-bold mb-3">🔔 Needs Attention</h6>
        @foreach ($attention as [$label, $count, $url])
          <a href="{{ $url }}" class="d-flex justify-content-between align-items-center py-2 text-decoration-none border-bottom">
            <span class="small text-dark">{{ $label }}</span>
            <span class="badge text-bg-{{ $count > 0 ? 'warning' : 'secondary' }}">{{ $count }}</span>
          </a>
        @endforeach
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-7">
      <div class="card p-3 h-100">
        <h6 class="fw-bold mb-3">🏆 Top Performing Agents <span class="text-secondary fw-normal small">· this month</span></h6>
        <table class="table table-sm align-middle mb-0">
          <thead class="text-secondary small"><tr><th>#</th><th>Agent</th><th>Tier</th><th class="text-end">Bookings</th><th class="text-end">Sales</th></tr></thead>
          <tbody class="small">
            @forelse ($topAgents as $a)
              <tr>
                <td>{{ $a->rank }}</td>
                <td class="fw-semibold">{{ $a->name }}</td>
                <td><span class="badge text-bg-secondary">{{ ucfirst($a->agent_tier) }}</span></td>
                <td class="text-end">{{ $a->bookings }}</td>
                <td class="text-end fw-semibold">RM {{ number_format($a->sales, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-secondary py-4">No agent sales this month yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-3 h-100">
        <h6 class="fw-bold mb-3">🔥 Top Selling Packages</h6>
        <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
          @forelse ($topPackages as $p)
            <li class="d-flex justify-content-between">
              <a href="{{ route('manage.packages.show', $p) }}" class="text-decoration-none text-dark text-truncate" style="max-width:70%">{{ $p->title }}</a>
              <span class="fw-semibold">{{ $p->bookings_count }} sold</span>
            </li>
          @empty
            <li class="text-secondary">No packages sold yet.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>

  <div class="card p-3 p-lg-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">Recent Bookings</h6>
      <div class="d-flex gap-2">
        <a href="{{ route('manage.bookings.index') }}" class="btn btn-sm btn-outline-primary">All Bookings</a>
        <a href="{{ route('manage.reports.index') }}" class="btn btn-sm btn-outline-secondary">Reports</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Booking</th><th>Customer</th><th>Package</th><th>Agent</th><th class="text-end">Total</th><th>Status</th></tr></thead>
        <tbody>
          @forelse ($recent as $b)
            <tr>
              <td class="small"><a href="{{ route('manage.bookings.show', $b) }}" class="text-decoration-none">{{ $b->booking_no }}</a></td>
              <td class="small">{{ $b->customer?->name ?? '—' }}</td>
              <td class="small text-truncate" style="max-width:200px">{{ $b->package?->title ?? '—' }}</td>
              <td class="small">{{ $b->agent?->name ?? 'Direct' }}</td>
              <td class="text-end small fw-semibold">RM {{ number_format($b->total_amount, 2) }}</td>
              <td><span class="badge text-bg-{{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-secondary py-4">No bookings yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
