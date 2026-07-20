@extends('layouts.admin')
@section('title', 'Commissions')
@section('console', 'Management')
@section('heading', 'Commission Ledger')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $kpis['pending_count'] }}</div><div class="text-secondary small">Pending Entries</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">RM {{ number_format($kpis['pending_amount'], 2) }}</div><div class="text-secondary small">Payable (pending)</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">RM {{ number_format($kpis['approved_amount'], 2) }}</div><div class="text-secondary small">Approved</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-secondary">RM {{ number_format($kpis['orphan_amount'], 2) }}</div><div class="text-secondary small">Orphan → HQ</div></div></div>
  </div>

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex flex-wrap gap-2" method="GET">
      <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Any status</option>
        @foreach (\App\Models\Commission::STATUS_BADGE as $k => $b)
          <option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>
        @endforeach
      </select>
      <select name="period" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">All periods</option>
        @foreach ($periods as $p)
          <option value="{{ $p }}" @selected(request('period') === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </form>
    @if (request('period'))
      <form method="POST" action="{{ route('manage.commission.approve-period') }}" onsubmit="return confirm('Approve ALL pending commissions for {{ request('period') }}?')">
        @csrf
        <input type="hidden" name="period" value="{{ request('period') }}">
        <button class="btn btn-sm btn-success">✓ Approve all — {{ request('period') }}</button>
      </form>
    @endif
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Booking</th><th>Earner</th><th>From Agent</th><th>Lvl</th><th class="text-end">Base</th><th>%</th><th class="text-end">Amount</th><th>Period</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($commissions as $c)
            <tr>
              <td class="small"><a href="{{ route('manage.bookings.show', $c->booking_id) }}" class="text-decoration-none">{{ $c->booking?->booking_no }}</a></td>
              <td class="small">@if($c->is_orphan)<span class="badge text-bg-secondary">HQ (orphan)</span>@else{{ $c->earner?->name ?? '—' }}@endif</td>
              <td class="small text-secondary">{{ $c->sourceAgent?->name ?? '—' }}</td>
              <td><span class="badge text-bg-primary">L{{ $c->level }}</span></td>
              <td class="text-end small">RM {{ number_format($c->base_amount, 2) }}</td>
              <td class="small">{{ rtrim(rtrim(number_format($c->percent, 2), '0'), '.') }}%</td>
              <td class="text-end fw-semibold">RM {{ number_format($c->amount, 2) }}</td>
              <td class="small">{{ $c->period }}</td>
              <td><span class="badge text-bg-{{ $c->statusBadge() }}">{{ ucfirst($c->status) }}</span></td>
              <td class="text-end">
                @if ($c->status === 'pending')
                  <form method="POST" action="{{ route('manage.commission.approve', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">✓</button></form>
                  <form method="POST" action="{{ route('manage.commission.reject', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger py-0">✕</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-secondary py-5">No commissions yet. They generate when a booking is fully paid.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $commissions->links() }}</div>
@endsection
