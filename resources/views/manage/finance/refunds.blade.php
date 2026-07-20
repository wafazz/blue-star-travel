@extends('layouts.admin')
@section('title', 'Refunds')
@section('console', 'Management')
@section('heading', 'Refunds')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $kpis['pending'] }}</div><div class="text-secondary small">Pending Refunds</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">RM {{ number_format($kpis['processed'], 2) }}</div><div class="text-secondary small">Total Processed</div></div></div>
    <div class="col-lg-6 d-flex align-items-center justify-content-end">
      <a href="{{ route('manage.finance.dashboard') }}" class="btn btn-sm btn-outline-secondary">← Finance Dashboard</a>
    </div>
  </div>

  <form class="d-flex gap-2 mb-3" method="GET">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (\App\Models\Refund::STATUS_BADGE as $k => $b)
        <option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>
      @endforeach
    </select>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Refund #</th><th>Booking</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($refunds as $r)
            <tr>
              <td class="fw-semibold small">{{ $r->refund_no }}</td>
              <td class="small"><a href="{{ route('manage.bookings.show', $r->booking_id) }}" class="text-decoration-none">{{ $r->booking?->booking_no }}</a></td>
              <td class="small">{{ $r->booking?->customer?->name ?? '—' }}</td>
              <td class="small">{{ $r->methodLabel() }}</td>
              <td class="text-end fw-semibold">RM {{ number_format($r->amount, 2) }}</td>
              <td class="small text-secondary" style="max-width:200px">{{ Str::limit($r->reason, 60) ?: '—' }}</td>
              <td><span class="badge text-bg-{{ $r->statusBadge() }}">{{ ucfirst($r->status) }}</span></td>
              <td class="text-end">
                @if ($r->status === 'pending')
                  <form method="POST" action="{{ route('manage.finance.refunds.approve', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">Approve</button></form>
                  <form method="POST" action="{{ route('manage.finance.refunds.reject', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger py-0">Reject</button></form>
                @elseif ($r->status === 'approved')
                  <form method="POST" action="{{ route('manage.finance.refunds.process', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary py-0">Mark Processed</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-secondary py-5">No refunds. Create one from a booking's page.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $refunds->links() }}</div>
@endsection
