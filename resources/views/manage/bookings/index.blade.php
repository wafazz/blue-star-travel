@extends('layouts.admin')
@section('title', 'Bookings')
@section('console', 'Management')
@section('heading', 'Bookings')

@section('content')
  {{-- An amendment sits on a booking that already looks Confirmed, so nothing in the
       status column would ever say it is waiting on staff. This is that alert. --}}
  @if ($counts['pending_amendment'] > 0 && request('needs') !== 'amendment')
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        ⚠ <strong>{{ $counts['pending_amendment'] }} amendment request{{ $counts['pending_amendment'] === 1 ? '' : 's' }}</strong>
        awaiting your approval — date changes and postponements do not move until you approve them.
      </div>
      <a href="{{ route('manage.bookings.index', ['needs' => 'amendment']) }}" class="btn btn-sm btn-warning">Review now</a>
    </div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['status' => 'pending_verification']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-info">{{ $counts['pending_verification'] }}</div><div class="text-secondary small">Pending Verification</div></div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['status' => 'needs_revision']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $counts['needs_revision'] }}</div><div class="text-secondary small">Needs Revision</div></div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['status' => 'waiting_provider_confirmation']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-primary">{{ $counts['waiting_provider_confirmation'] }}</div><div class="text-secondary small">Waiting Provider</div></div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['status' => 'confirmed']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-success">{{ $counts['confirmed'] }}</div><div class="text-secondary small">Confirmed</div></div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['status' => 'postponed']) }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $counts['postponed'] }}</div><div class="text-secondary small">Postponed</div></div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index', ['needs' => 'amendment']) }}" class="text-decoration-none">
        <div class="card p-3 {{ $counts['pending_amendment'] > 0 ? 'border-warning' : '' }}">
          <div class="fs-4 fw-bold text-warning">{{ $counts['pending_amendment'] }}</div>
          <div class="text-secondary small">Amendments to Review</div>
        </div>
      </a>
    </div>
    <div class="col-6 col-lg">
      <a href="{{ route('manage.bookings.index') }}" class="text-decoration-none">
        <div class="card p-3"><div class="fs-4 fw-bold">All</div><div class="text-secondary small">Clear filters</div></div>
      </a>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
      {{-- Searching inside the amendment queue must not silently drop the queue. --}}
      @if (request('needs') === 'amendment')
        <input type="hidden" name="needs" value="amendment">
        <span class="badge text-bg-warning">⚠ Awaiting approval
          <a href="{{ route('manage.bookings.index') }}" class="text-dark text-decoration-none ms-1">✕</a>
        </span>
      @endif
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search booking # / customer…" style="min-width:220px">
      <span class="small text-secondary">Check-in</span>
      <input type="date" name="checkin_from" value="{{ request('checkin_from') }}" class="form-control form-control-sm" style="width:auto" title="Check-in from" aria-label="Check-in from">
      <span class="small text-secondary">to</span>
      <input type="date" name="checkin_to" value="{{ request('checkin_to') }}" class="form-control form-control-sm" style="width:auto" title="Check-in to" aria-label="Check-in to">
      <span class="small text-secondary">Check-out</span>
      <input type="date" name="checkout_from" value="{{ request('checkout_from') }}" class="form-control form-control-sm" style="width:auto" title="Check-out from" aria-label="Check-out from">
      <span class="small text-secondary">to</span>
      <input type="date" name="checkout_to" value="{{ request('checkout_to') }}" class="form-control form-control-sm" style="width:auto" title="Check-out to" aria-label="Check-out to">
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
            <th>Phone</th>
            <th>Agent</th>
            <th>Check-in / Check-out</th>
            <th>Pack</th>
            <th class="text-end">Total</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th>Agent note</th>
            <th>Remark</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bookings as $booking)
            <tr class="{{ $booking->pending_amendments_count ? 'table-warning' : '' }}">
              <td class="fw-semibold">{{ $booking->booking_no }}</td>
              <td class="small">{{ $booking->package?->title ?? '—' }}</td>
              <td class="small">{{ $booking->customer?->name ?? '—' }}</td>
              <td class="small text-nowrap">
                @if ($booking->customer?->phone)
                  <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->customer->phone) }}" class="text-decoration-none">{{ $booking->customer->phone }}</a>
                @else
                  —
                @endif
              </td>
              <td class="small text-secondary">{{ $booking->agent?->name ?? '—' }}</td>
              <td class="small text-nowrap">
                {{ $booking->arrivalDate()?->format('d M Y') ?? '—' }}
                <span class="text-secondary">→</span>
                {{ $booking->returnDate()?->format('d M Y') ?? '—' }}
              </td>
              <td class="small text-nowrap">
                @php
                  $pack = array_filter([
                      (int) $booking->adults_total > 0 ? $booking->adults_total . ' Adult' . ((int) $booking->adults_total > 1 ? 's' : '') : null,
                      (int) $booking->children_total > 0 ? $booking->children_total . ' Child' . ((int) $booking->children_total > 1 ? 'ren' : '') : null,
                      (int) $booking->seniors_total > 0 ? $booking->seniors_total . ' Senior' . ((int) $booking->seniors_total > 1 ? 's' : '') : null,
                      (int) $booking->infants_total > 0 ? $booking->infants_total . ' Infant' . ((int) $booking->infants_total > 1 ? 's' : '') : null,
                  ]);
                @endphp
                {{ $pack ? implode(', ', $pack) : '—' }}
              </td>
              <td class="text-end">RM {{ number_format($booking->total_amount, 2) }}</td>
              <td class="text-end {{ $booking->balance() > 0 ? 'text-danger' : 'text-success' }}">RM {{ number_format($booking->balance(), 2) }}</td>
              <td>
                <span class="badge text-bg-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
                @if ($booking->pending_amendments_count)
                  <span class="badge text-bg-warning d-block mt-1" title="An amendment request is waiting for approval">⚠ Needs approval</span>
                @endif
              </td>
              <td class="small" style="max-width:200px">
                @if ($booking->agent_note)
                  <span title="{{ $booking->agent_note }}">{{ \Illuminate\Support\Str::limit($booking->agent_note, 60) }}</span>
                @else
                  <span class="text-secondary">—</span>
                @endif
              </td>
              {{-- Staff-only remark: saved in place so nobody has to open the booking to jot one down. --}}
              <td style="min-width:240px">
                <form method="POST" action="{{ route('manage.bookings.remark', $booking) }}" class="d-flex gap-1">
                  @csrf
                  <input type="text" name="internal_notes" value="{{ $booking->internal_notes }}" maxlength="1000"
                         class="form-control form-control-sm" placeholder="Add remark…"
                         aria-label="Remark for {{ $booking->booking_no }}">
                  <button class="btn btn-sm btn-outline-secondary" title="Save remark">Save</button>
                </form>
              </td>
              <td class="text-end"><a href="{{ route('manage.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="13" class="text-center text-secondary py-5">No bookings found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $bookings->links() }}</div>
@endsection
