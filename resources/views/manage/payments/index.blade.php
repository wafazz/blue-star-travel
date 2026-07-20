@extends('layouts.admin')
@section('title', 'Payments')
@section('console', 'Management')
@section('heading', 'Payments')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $kpis['pending'] }}</div><div class="text-secondary small">Pending Verification</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">RM {{ number_format($kpis['pending_amount'], 2) }}</div><div class="text-secondary small">Pending Amount</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">RM {{ number_format($kpis['verified_amount'], 2) }}</div><div class="text-secondary small">Total Verified</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-primary">RM {{ number_format($kpis['today_amount'], 2) }}</div><div class="text-secondary small">Verified Today</div></div></div>
  </div>

  <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search ref / booking #…" style="min-width:220px">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $k => $label)
        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="method" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any method</option>
      @foreach (\App\Models\Payment::METHODS as $k => $label)
        <option value="{{ $k }}" @selected(request('method') === $k)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Date</th><th>Booking</th><th>Customer</th><th>Method</th><th class="text-capitalize">Type</th><th class="text-end">Amount</th><th>Status</th><th>Slip</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($payments as $pay)
            <tr>
              <td class="small">{{ optional($pay->paid_at)->format('d M Y') ?? optional($pay->created_at)->format('d M Y') }}</td>
              <td class="small"><a href="{{ route('manage.bookings.show', $pay->booking_id) }}" class="fw-semibold text-decoration-none">{{ $pay->booking?->booking_no }}</a></td>
              <td class="small">{{ $pay->booking?->customer?->name ?? '—' }}</td>
              <td class="small">{{ $pay->methodLabel() }}@if($pay->reference)<div class="text-secondary" style="font-size:.72rem">{{ $pay->reference }}</div>@endif</td>
              <td class="small text-capitalize">{{ $pay->type }}</td>
              <td class="text-end fw-semibold">RM {{ number_format($pay->amount, 2) }}</td>
              <td><span class="badge text-bg-{{ $pay->status === 'verified' ? 'success' : ($pay->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($pay->status) }}</span></td>
              <td>@if($pay->slip_path)<a href="{{ route('payments.slip', $pay) }}" target="_blank" class="small">View</a>@else<span class="text-secondary">—</span>@endif</td>
              <td class="text-end">
                @if ($pay->status === 'pending')
                  <form method="POST" action="{{ route('manage.payments.verify', $pay) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">✓</button></form>
                  <form method="POST" action="{{ route('manage.payments.reject', $pay) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger py-0">✕</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-secondary py-5">No payments found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $payments->links() }}</div>
@endsection
