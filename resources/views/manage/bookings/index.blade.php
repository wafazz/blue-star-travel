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

  @php
    $filterKeys = ['q', 'status', 'checkin_from', 'checkin_to', 'checkout_from', 'checkout_to'];
    $chipLabels = [
        'q' => 'Search', 'status' => 'Status',
        'checkin_from' => 'Check-in from', 'checkin_to' => 'Check-in to',
        'checkout_from' => 'Check-out from', 'checkout_to' => 'Check-out to',
    ];
    $activeFilters = collect($filterKeys)->filter(fn ($k) => filled(request($k)))->values();
    // Sort headers: clicking the active column flips direction; the other filters are kept.
    $sortLink = fn (string $key) => route('manage.bookings.index', array_merge(
        \Illuminate\Support\Arr::except(request()->query(), 'page'),
        ['sort' => $key, 'dir' => (request('sort') === $key && request('dir') === 'desc') ? 'asc' : 'desc']
    ));
    $sortMark = fn (string $key) => request('sort') === $key ? (request('dir') === 'asc' ? ' ▲' : ' ▼') : '';
  @endphp

  <style>
    /* Desktop: a sortable header. Phone: each booking becomes a labelled card. */
    .bk-sort{color:inherit;text-decoration:none;white-space:nowrap}
    .bk-sort:hover{text-decoration:underline}
    .bk-row{cursor:pointer}
    .bk-remark form{min-width:240px}
    .bk-remark input{min-width:0}
    @media (max-width: 767.98px) {
      .bk-table thead{display:none}
      .bk-table, .bk-table tbody, .bk-table tr, .bk-table td{display:block;width:100%}
      .bk-table tr{padding:12px 14px;border-bottom:1px solid #e9ecef}
      .bk-table td{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border:0;padding:5px 0;text-align:right}
      .bk-table td::before{content:attr(data-label);flex:0 0 38%;text-align:left;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:#6c757d}
      .bk-table td.bk-actions{justify-content:flex-end}
      .bk-table td.bk-actions::before,.bk-table td.bk-empty::before{display:none}
      .bk-table td.bk-empty{display:block;text-align:center}
      .bk-table td.bk-remark{display:block;text-align:left;padding-top:8px}
      .bk-table td.bk-remark::before{display:block;margin-bottom:4px}
      .bk-remark form{min-width:0;width:100%}
      .bk-table td.bk-note span{overflow-wrap:anywhere}
    }
  </style>

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
    {{-- Filters apply as you type (search) or as you change them (dates, status). The Filter button is a no-JS fallback. --}}
    <form id="bkFilter" class="d-flex flex-wrap gap-2 align-items-center" method="GET" action="{{ route('manage.bookings.index') }}">
      {{-- Searching inside the amendment queue must not silently drop the queue. --}}
      @if (request('needs') === 'amendment')
        <input type="hidden" name="needs" value="amendment">
        <span class="badge text-bg-warning">⚠ Awaiting approval
          <a href="{{ route('manage.bookings.index') }}" class="text-dark text-decoration-none ms-1">✕</a>
        </span>
      @endif
      @if (request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
      @if (request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
      <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search booking # / customer…" style="min-width:220px" data-autosubmit="search" aria-label="Search bookings">
      <span class="small text-secondary">Check-in</span>
      <input type="date" name="checkin_from" value="{{ request('checkin_from') }}" class="form-control form-control-sm" style="width:auto" title="Check-in from" aria-label="Check-in from" data-autosubmit="change">
      <span class="small text-secondary">to</span>
      <input type="date" name="checkin_to" value="{{ request('checkin_to') }}" class="form-control form-control-sm" style="width:auto" title="Check-in to" aria-label="Check-in to" data-autosubmit="change">
      <span class="small text-secondary">Check-out</span>
      <input type="date" name="checkout_from" value="{{ request('checkout_from') }}" class="form-control form-control-sm" style="width:auto" title="Check-out from" aria-label="Check-out from" data-autosubmit="change">
      <span class="small text-secondary">to</span>
      <input type="date" name="checkout_to" value="{{ request('checkout_to') }}" class="form-control form-control-sm" style="width:auto" title="Check-out to" aria-label="Check-out to" data-autosubmit="change">
      <select name="status" class="form-select form-select-sm" style="width:auto" data-autosubmit="change" aria-label="Status">
        <option value="">Any status</option>
        @foreach (\App\Models\Booking::STATUSES as $k => $label)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary bk-filter-btn">Filter</button>
    </form>
    <a href="{{ route('manage.bookings.create') }}" class="btn btn-brand btn-sm">＋ New Booking</a>
  </div>

  @if ($activeFilters->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3" aria-label="Active filters">
      @foreach ($activeFilters as $key)
        @php $value = $key === 'status' ? (\App\Models\Booking::STATUSES[request($key)] ?? request($key)) : request($key); @endphp
        <a href="{{ route('manage.bookings.index', \Illuminate\Support\Arr::except(request()->query(), [$key, 'page'])) }}"
           class="badge rounded-pill text-bg-light border text-dark text-decoration-none" title="Remove this filter">
          {{ $chipLabels[$key] }}: {{ $value }} ✕
        </a>
      @endforeach
      <a href="{{ route('manage.bookings.index') }}" class="small">Clear all</a>
    </div>
  @else
    <div class="mb-3"></div>
  @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 bk-table">
        <thead class="table-light">
          <tr>
            <th>Booking #</th>
            <th>Package</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Agent</th>
            <th>
              <a href="{{ $sortLink('checkin') }}" class="bk-sort">Check-in{{ $sortMark('checkin') }}</a>
              /
              <a href="{{ $sortLink('checkout') }}" class="bk-sort">Check-out{{ $sortMark('checkout') }}</a>
            </th>
            <th>Pack</th>
            <th class="text-end"><a href="{{ $sortLink('total') }}" class="bk-sort">Total{{ $sortMark('total') }}</a></th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th>Agent note</th>
            <th>Remark</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bookings as $booking)
            <tr class="bk-row {{ $booking->pending_amendments_count ? 'table-warning' : '' }}" data-href="{{ route('manage.bookings.show', $booking) }}">
              <td class="fw-semibold" data-label="Booking #">{{ $booking->booking_no }}</td>
              <td class="small" data-label="Package">{{ $booking->package?->title ?? '—' }}</td>
              <td class="small" data-label="Customer">{{ $booking->customer?->name ?? '—' }}</td>
              <td class="small text-nowrap" data-label="Phone">
                @if ($booking->customer?->phone)
                  <a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->customer->phone) }}" class="text-decoration-none">{{ $booking->customer->phone }}</a>
                @else
                  —
                @endif
              </td>
              <td class="small text-secondary" data-label="Agent">{{ $booking->agent?->name ?? '—' }}</td>
              <td class="small text-nowrap" data-label="Check-in / Check-out">
                {{ $booking->arrivalDate()?->format('d M Y') ?? '—' }}
                <span class="text-secondary">→</span>
                {{ $booking->returnDate()?->format('d M Y') ?? '—' }}
              </td>
              <td class="small text-nowrap" data-label="Pack">
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
              <td class="text-end" data-label="Total">RM {{ number_format($booking->total_amount, 2) }}</td>
              <td class="text-end {{ $booking->balance() > 0 ? 'text-danger' : 'text-success' }}" data-label="Balance">RM {{ number_format($booking->balance(), 2) }}</td>
              <td data-label="Status">
                <span class="badge text-bg-{{ $booking->statusBadge() }}">{{ $booking->statusLabel() }}</span>
                @if ($booking->pending_amendments_count)
                  <span class="badge text-bg-warning d-block mt-1" title="An amendment request is waiting for approval">⚠ Needs approval</span>
                @endif
              </td>
              <td class="small bk-note" data-label="Agent note">
                @if ($booking->agent_note)
                  <span title="{{ $booking->agent_note }}">{{ \Illuminate\Support\Str::limit($booking->agent_note, 60) }}</span>
                @else
                  <span class="text-secondary">—</span>
                @endif
              </td>
              {{-- Staff-only remark: saved in place (no page reload) so nobody opens the booking to jot one down. --}}
              <td class="bk-remark" data-label="Remark">
                <form method="POST" action="{{ route('manage.bookings.remark', $booking) }}" class="d-flex gap-1 align-items-center remark-form">
                  @csrf
                  <input type="text" name="internal_notes" value="{{ $booking->internal_notes }}" maxlength="1000"
                         class="form-control form-control-sm" placeholder="Add remark…"
                         aria-label="Remark for {{ $booking->booking_no }}">
                  <button class="btn btn-sm btn-outline-secondary" title="Save remark">Save</button>
                  <span class="remark-status small text-success text-nowrap" aria-live="polite"></span>
                </form>
              </td>
              <td class="text-end bk-actions"><a href="{{ route('manage.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="13" class="text-center text-secondary py-5 bk-empty">No bookings found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $bookings->links() }}</div>

  <script>
    (function () {
      // Search applies 400 ms after the last keystroke; dropdowns and dates apply on change.
      const form = document.getElementById('bkFilter');
      const fallbackBtn = form.querySelector('.bk-filter-btn');
      fallbackBtn.classList.add('d-none');
      let timer;
      form.querySelectorAll('[data-autosubmit="change"]').forEach(function (el) {
        el.addEventListener('change', function () { form.submit(); });
      });
      form.querySelector('[data-autosubmit="search"]').addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 400);
      });

      // A click anywhere on a row opens the booking, unless it hit a control inside the row.
      document.querySelectorAll('tr.bk-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
          if (e.target.closest('a, button, input, select, textarea, label, form')) return;
          window.location = row.dataset.href;
        });
      });

      // Remark: save over JSON and show the result next to the button.
      document.querySelectorAll('form.remark-form').forEach(function (f) {
        f.addEventListener('submit', async function (e) {
          e.preventDefault();
          const status = f.querySelector('.remark-status');
          const btn = f.querySelector('button');
          btn.disabled = true;
          status.className = 'remark-status small text-secondary text-nowrap';
          status.textContent = 'Saving…';
          try {
            const res = await fetch(f.action, {
              method: 'POST',
              body: new FormData(f),
              credentials: 'same-origin',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(function () { return {}; });
            if (res.ok) {
              status.className = 'remark-status small text-success text-nowrap';
              status.textContent = 'Saved ✓';
            } else {
              const first = data.errors ? Object.values(data.errors)[0][0] : 'Could not save.';
              status.className = 'remark-status small text-danger text-nowrap';
              status.textContent = first;
            }
          } catch (err) {
            status.className = 'remark-status small text-danger text-nowrap';
            status.textContent = 'Network error — try again.';
          } finally {
            btn.disabled = false;
            setTimeout(function () {
              if (status.classList.contains('text-success')) status.textContent = '';
            }, 2000);
          }
        });
      });
    })();
  </script>
@endsection
