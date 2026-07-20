@extends('layouts.admin')
@section('title', 'Bookings')
@section('console', 'Management')
@section('heading', 'Bookings')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <a href="{{ route('manage.bookings.index', ['status' => 'pending_verification']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-info">{{ $counts['pending_verification'] }}</div><div class="text-secondary small">Pending Verification</div></div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="{{ route('manage.bookings.index', ['status' => 'waiting_provider_confirmation']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-primary">{{ $counts['waiting_provider_confirmation'] }}</div><div class="text-secondary small">Waiting Provider</div></div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="{{ route('manage.bookings.index', ['status' => 'confirmed']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-success">{{ $counts['confirmed'] }}</div><div class="text-secondary small">Confirmed</div></div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="{{ route('manage.bookings.index') }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold">All</div><div class="text-secondary small">Clear filters</div></div>
      </a>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search booking # / customer…" style="min-width:220px">
      <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Any status</option>
        @foreach (\App\Models\Booking::STATUSES as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="{{ route('manage.bookings.create') }}" class="btn btn-brand btn-sm">＋ New Booking</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Booking #</th>
            <th>Package</th>
            <th>Customer</th>
            <th>Agent</th>
            <th>Travel</th>
            <th class="text-end">Total</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bookings as $booking)
            <tr>
              <td class="fw-semibold">{{ $booking->booking_no }}</td>
              <td class="small">{{ $booking->package?->title ?? '—' }}</td>
              <td class="small">{{ $booking->customer?->name ?? '—' }}</td>
              <td class="small text-secondary">{{ $booking->agent?->name ?? '—' }}</td>
              <td class="small">{{ optional($booking->travel_date)->format('d M Y') ?? '—' }}</td>
              <td class="text-end">RM {{ number_format($booking->total_amount, 2) }}</td>
              <td class="text-end {{ $booking->balance() > 0 ? 'text-danger' : 'text-success' }}">RM {{ number_format($booking->balance(), 2) }}</td>
              <td><span class="badge text-bg-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span></td>
              <td class="text-end"><a href="{{ route('manage.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-secondary py-5">No bookings found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $bookings->links() }}</div>
@endsection
