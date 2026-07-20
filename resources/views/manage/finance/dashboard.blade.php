@extends('layouts.admin')
@section('title', 'Finance')
@section('console', 'Management')
@section('heading', 'Finance Dashboard')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="card h-100 p-3"><span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-success bg-opacity-10" style="width:40px;height:40px">💰</span>
        <div class="fs-4 fw-bold mt-3">RM {{ number_format($revenue, 2) }}</div><div class="text-secondary small">Total Revenue (verified)</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 p-3"><span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-primary bg-opacity-10" style="width:40px;height:40px">📈</span>
        <div class="fs-4 fw-bold mt-3">RM {{ number_format($monthRevenue, 2) }}</div><div class="text-secondary small">This Month</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 p-3"><span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-warning bg-opacity-10" style="width:40px;height:40px">⏳</span>
        <div class="fs-4 fw-bold mt-3">RM {{ number_format($outstanding, 2) }}</div><div class="text-secondary small">Outstanding Balance</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 p-3"><span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-danger bg-opacity-10" style="width:40px;height:40px">↩️</span>
        <div class="fs-4 fw-bold mt-3">RM {{ number_format($refundedTotal, 2) }}</div><div class="text-secondary small">Refunded</div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h6 class="fw-bold mb-0">Revenue — last 6 months</h6>
          <span class="badge text-bg-warning">Pending: RM {{ number_format($pendingPayAmt, 2) }}</span>
        </div>
        <div class="d-flex align-items-end justify-content-between gap-2" style="height:220px">
          @foreach ($trend as $t)
            <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height:100%">
              <div class="small fw-semibold mb-1" style="font-size:.7rem">{{ $t['value'] > 0 ? 'RM ' . number_format($t['value'] / 1000, 1) . 'k' : '' }}</div>
              <div class="w-100 rounded-top" style="background:linear-gradient(180deg,#1466ff,#0b3fd1);height:{{ max(2, round($t['value'] / $trendMax * 100)) }}%;min-height:2px;transition:.3s"></div>
              <div class="text-secondary small mt-2">{{ $t['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4 h-100">
        <h6 class="fw-bold mb-3">Bookings by Status</h6>
        @php $totalB = max(1, $bookingStatus->sum()); @endphp
        @forelse (\App\Models\Booking::STATUSES as $k => $label)
          @php $c = $bookingStatus[$k] ?? 0; @endphp
          @if ($c > 0)
            <div class="mb-2">
              <div class="d-flex justify-content-between small mb-1"><span>{{ $label }}</span><span class="fw-semibold">{{ $c }}</span></div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-{{ \App\Models\Booking::STATUS_BADGE[$k] ?? 'secondary' }}" style="width:{{ round($c / $totalB * 100) }}%"></div></div>
            </div>
          @endif
        @empty
        @endforelse
      </div>
    </div>
  </div>

  <div class="card p-3 p-lg-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">Recent Verified Payments</h6>
      <div class="d-flex gap-2">
        <a href="{{ route('manage.payments.index') }}" class="btn btn-sm btn-outline-primary">All Payments</a>
        <a href="{{ route('manage.finance.refunds') }}" class="btn btn-sm btn-outline-secondary">Refunds</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Booking</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
          @forelse ($recent as $pay)
            <tr>
              <td class="small">{{ optional($pay->verified_at)->format('d M Y') }}</td>
              <td class="small"><a href="{{ route('manage.bookings.show', $pay->booking_id) }}" class="text-decoration-none">{{ $pay->booking?->booking_no }}</a></td>
              <td class="small">{{ $pay->booking?->customer?->name ?? '—' }}</td>
              <td class="small">{{ $pay->methodLabel() }}</td>
              <td class="text-end fw-semibold text-success">RM {{ number_format($pay->amount, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-secondary py-4">No verified payments yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
