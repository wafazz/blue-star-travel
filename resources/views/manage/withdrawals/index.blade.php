@extends('layouts.admin')
@section('title', 'Withdrawals')
@section('console', 'Management')
@section('heading', 'Agent Withdrawals')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $kpis['pending'] }}</div><div class="text-secondary small">Pending Requests</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">RM {{ number_format($kpis['pending_amt'], 2) }}</div><div class="text-secondary small">Awaiting Payout</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">RM {{ number_format($kpis['paid_amt'], 2) }}</div><div class="text-secondary small">Total Paid</div></div></div>
  </div>

  <form class="d-flex gap-2 mb-3" method="GET">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (\App\Models\Withdrawal::STATUS_BADGE as $k => $b)
        <option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>
      @endforeach
    </select>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Ref</th><th>Agent</th><th class="text-end">Amount</th><th>Bank</th><th>Requested</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($withdrawals as $w)
            <tr>
              <td class="fw-semibold small">{{ $w->withdrawal_no }}</td>
              <td class="small">{{ $w->user?->name }}</td>
              <td class="text-end fw-semibold">RM {{ number_format($w->amount, 2) }}</td>
              <td class="small">{{ $w->bank_name }}<div class="text-secondary" style="font-size:.72rem">{{ $w->bank_account_no }} · {{ $w->bank_account_name }}</div></td>
              <td class="small">{{ $w->created_at->format('d M Y') }}</td>
              <td><span class="badge text-bg-{{ $w->statusBadge() }}">{{ ucfirst($w->status) }}</span></td>
              <td class="text-end text-nowrap">
                @if ($w->status === 'pending')
                  <form method="POST" action="{{ route('manage.withdrawals.approve', $w) }}" class="d-inline">@csrf<button class="btn btn-sm btn-info py-0">Approve</button></form>
                @endif
                @if (in_array($w->status, ['pending', 'approved']))
                  <form method="POST" action="{{ route('manage.withdrawals.paid', $w) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">Mark Paid</button></form>
                  <form method="POST" action="{{ route('manage.withdrawals.reject', $w) }}" class="d-inline" onsubmit="return confirm('Reject & return funds to agent wallet?')">@csrf<button class="btn btn-sm btn-outline-danger py-0">Reject</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-secondary py-5">No withdrawal requests.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $withdrawals->links() }}</div>
@endsection
