@extends('layouts.admin')
@section('title', 'Redemptions')
@section('console', 'Management')
@section('heading', 'Reward Redemptions')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $kpis['pending'] }}</div><div class="text-secondary small">Pending</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-primary">{{ number_format($kpis['points_spent']) }}</div><div class="text-secondary small">Points Redeemed</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">RM {{ number_format($kpis['cash_value'], 2) }}</div><div class="text-secondary small">Reward Value</div></div></div>
  </div>

  <form class="d-flex gap-2 mb-3" method="GET">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (\App\Models\Redemption::STATUS_BADGE as $k => $b)
        <option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>
      @endforeach
    </select>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Ref</th><th>Agent</th><th>Reward</th><th class="text-end">Points</th><th class="text-end">Value</th><th>Requested</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($redemptions as $r)
            <tr>
              <td class="fw-semibold small">{{ $r->redemption_no }}</td>
              <td class="small">{{ $r->user?->name }}</td>
              <td class="small">{{ $r->typeLabel() }}</td>
              <td class="text-end">{{ number_format($r->points_cost) }}</td>
              <td class="text-end">RM {{ number_format($r->cash_value, 2) }}</td>
              <td class="small">{{ $r->created_at->format('d M Y') }}</td>
              <td><span class="badge text-bg-{{ $r->statusBadge() }}">{{ ucfirst($r->status) }}</span></td>
              <td class="text-end text-nowrap">
                @if ($r->status === 'pending')
                  <form method="POST" action="{{ route('manage.redemptions.approve', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-info py-0">Approve</button></form>
                @endif
                @if (in_array($r->status, ['pending', 'approved']))
                  <form method="POST" action="{{ route('manage.redemptions.fulfill', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">Fulfill</button></form>
                  <form method="POST" action="{{ route('manage.redemptions.reject', $r) }}" class="d-inline" onsubmit="return confirm('Reject & return points?')">@csrf<button class="btn btn-sm btn-outline-danger py-0">Reject</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-secondary py-5">No redemptions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $redemptions->links() }}</div>
@endsection
