@extends('layouts.admin')
@section('title', 'Admin Dashboard')
@section('console', 'Admin')
@section('heading', 'Admin Dashboard')

@section('content')
  <div class="row g-3">
    @foreach ($cards as [$label, $value, $icon, $color, $url])
      <div class="col-6 col-lg-4">
        <a href="{{ $url }}" class="text-decoration-none">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <span class="rounded-3 d-flex align-items-center justify-content-center fs-4 text-bg-{{ $color }} bg-opacity-10" style="width:52px;height:52px">{{ $icon }}</span>
              <div>
                <div class="fs-4 fw-bold text-dark">{{ $value }}</div>
                <div class="text-secondary small">{{ $label }}</div>
              </div>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-7">
      <div class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">📋 Processing Queue <span class="text-secondary fw-normal small">· oldest first</span></h6>
          <a href="{{ route('manage.bookings.index') }}" class="btn btn-sm btn-outline-primary">All</a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Booking</th><th>Customer</th><th>Waiting</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($queue as $b)
                <tr>
                  <td class="small"><a href="{{ route('manage.bookings.show', $b) }}" class="text-decoration-none">{{ $b->booking_no }}</a></td>
                  <td class="small">{{ $b->customer?->name ?? '—' }}</td>
                  <td class="small text-secondary">{{ $b->created_at->diffForHumans(null, true) }}</td>
                  <td><span class="badge text-bg-{{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span></td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-secondary py-4">Queue is clear 🎉</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">💳 Payments to Verify</h6>
          <a href="{{ route('manage.payments.index') }}" class="btn btn-sm btn-outline-primary">All</a>
        </div>
        @forelse ($unverifiedPayments as $pay)
          <a href="{{ route('manage.bookings.show', $pay->booking_id) }}" class="d-flex justify-content-between align-items-center py-2 text-decoration-none border-bottom">
            <span class="small text-dark">
              {{ $pay->booking?->booking_no }}
              <span class="text-secondary">· {{ $pay->booking?->customer?->name ?? '—' }}</span>
            </span>
            <span class="small fw-semibold">RM {{ number_format($pay->amount, 2) }}</span>
          </a>
        @empty
          <div class="text-secondary small py-3">Nothing awaiting verification.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="card p-3 p-lg-4 mt-3">
    <h6 class="fw-bold mb-3">✈️ Departing Today</h6>
    @forelse ($todayTravel as $b)
      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <div class="small">
          <a href="{{ route('manage.bookings.show', $b) }}" class="text-decoration-none fw-semibold">{{ $b->booking_no }}</a>
          <span class="text-secondary">· {{ $b->customer?->name }} · {{ $b->package?->title }}</span>
        </div>
        <span class="badge text-bg-primary">{{ $b->total_pax }} pax</span>
      </div>
    @empty
      <div class="text-secondary small">No departures scheduled for today.</div>
    @endforelse
  </div>
@endsection
