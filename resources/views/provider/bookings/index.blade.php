@extends('layouts.admin')
@section('title', 'Incoming Bookings')
@section('console', 'Provider')
@section('heading', 'Incoming Bookings')

@section('nav')
  <a class="nav-link px-2 py-2" href="{{ route('provider.dashboard') }}">🏠 Dashboard</a>
  <a class="nav-link active px-2 py-2" href="{{ route('provider.bookings.index') }}">📋 Incoming Bookings</a>
@endsection

@section('content')
  @if ($pending)
    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 small">You have <strong>{{ $pending }}</strong> booking(s) awaiting your confirmation.</div>
  @endif

  <div class="d-flex gap-2 mb-3">
    <form method="GET" class="d-flex gap-2">
      <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Any status</option>
        @foreach (\App\Models\Booking::STATUSES as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Booking #</th><th>Package</th><th>Customer</th><th>Travel</th><th>Pax</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($bookings as $booking)
            <tr>
              <td class="fw-semibold">{{ $booking->booking_no }}</td>
              <td class="small">{{ $booking->package?->title ?? '—' }}</td>
              <td class="small">{{ $booking->customer?->name ?? '—' }}</td>
              <td class="small">{{ optional($booking->travel_date)->format('d M Y') ?? '—' }}</td>
              <td class="small">{{ $booking->total_pax }}</td>
              <td><span class="badge text-bg-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span></td>
              <td class="text-end"><a href="{{ route('provider.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-secondary py-5">No bookings routed to you yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $bookings->links() }}</div>
@endsection
