@extends('layouts.admin')
@section('title', $agent->name)
@section('console', 'Management')
@section('heading', 'Agent Profile')

@php
  $tierBadge = ['agent' => 'secondary', 'assistant_mentor' => 'warning', 'mentor' => 'info'];
  $statusBadge = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger'];
@endphp

@section('content')
  <a href="{{ route('manage.agents.index') }}" class="btn btn-sm btn-link text-decoration-none px-0 mb-2">← Back to agents</a>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-4 mb-3">
        <div class="d-flex align-items-center gap-3">
          <span class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width:60px;height:60px">{{ $agent->initials() }}</span>
          <div class="flex-fill">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <h5 class="fw-bold mb-0">{{ $agent->name }}</h5>
              <span class="badge text-bg-{{ $tierBadge[$agent->agent_tier] ?? 'secondary' }}">{{ $agent->tierLabel() }}</span>
              <span class="badge text-bg-{{ $statusBadge[$agent->status] ?? 'secondary' }}">{{ ucfirst($agent->status) }}</span>
            </div>
            <div class="text-secondary small">{{ $agent->agent_code }} · {{ $agent->email }} · {{ $agent->phone ?: 'no phone' }}</div>
            <div class="text-secondary small">Upline: {{ $agent->referrer?->name ?? '— (root)' }} · Joined {{ $agent->created_at?->format('d M Y') }}</div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-5 fw-bold text-primary">RM {{ number_format($salesTotal, 2) }}</div><div class="text-secondary small">Lifetime Sales</div></div></div>
        <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-5 fw-bold text-success">RM {{ number_format($commissionEarned, 2) }}</div><div class="text-secondary small">Commission Earned</div></div></div>
        <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-5 fw-bold text-dark">RM {{ number_format((float) ($agent->wallet->balance ?? 0), 2) }}</div><div class="text-secondary small">Wallet Balance</div></div></div>
        <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-5 fw-bold text-info">{{ $network }}</div><div class="text-secondary small">Total Network</div></div></div>
      </div>

      <div class="card p-4 mb-3">
        <h6 class="fw-bold mb-3">Recent Commissions</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="text-secondary small"><tr><th>Booking</th><th>Level</th><th class="text-end">Amount</th><th>Status</th><th>Period</th></tr></thead>
            <tbody>
              @forelse ($commissions as $c)
                <tr>
                  <td class="small">{{ $c->booking?->booking_no ?? '—' }}</td>
                  <td>@if($c->is_hq)<span class="badge text-bg-dark">HQ</span>@else<span class="badge text-bg-primary">L{{ $c->level }}</span>@endif</td>
                  <td class="text-end small fw-semibold">RM {{ number_format($c->amount, 2) }}</td>
                  <td><span class="badge text-bg-{{ $c->statusBadge() }}">{{ ucfirst($c->status) }}</span></td>
                  <td class="small text-secondary">{{ $c->period }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-secondary py-3">No commissions yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card p-4">
        <h6 class="fw-bold mb-3">Recent Bookings</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="text-secondary small"><tr><th>Booking</th><th>Customer</th><th>Package</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($bookings as $b)
                <tr>
                  <td class="small"><a href="{{ route('manage.bookings.show', $b) }}" class="text-decoration-none">{{ $b->booking_no }}</a></td>
                  <td class="small">{{ $b->customer?->name ?? '—' }}</td>
                  <td class="small">{{ $b->package?->title ?? '—' }}</td>
                  <td class="text-end small">RM {{ number_format($b->total_amount, 2) }}</td>
                  <td><span class="badge text-bg-{{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span></td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-secondary py-3">No bookings yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-4 mb-3">
        <h6 class="fw-bold mb-3">🏆 Rank Progress</h6>
        @if ($nextTier)
          @php
            $reqTierLabel = \App\Models\User::tierLabelFor($tierProgress['required_tier']);
            $dPct = $tierRule['min_tier_downlines'] > 0 ? min(100, round($tierProgress['tier_downlines'] / $tierRule['min_tier_downlines'] * 100)) : 100;
            $pPct = $tierRule['min_packs'] > 0 ? min(100, round($tierProgress['packs'] / $tierRule['min_packs'] * 100)) : 100;
          @endphp
          <div class="small text-secondary mb-2">Next: <strong>{{ \App\Models\User::tierLabelFor($nextTier) }}</strong> {{ \App\Models\User::TIER_ICONS[$nextTier] }} · <span class="text-body-secondary">period {{ $period['label'] }}</span></div>
          <div class="d-flex justify-content-between small"><span>{{ $reqTierLabel }}s in downline</span><span class="fw-semibold">{{ $tierProgress['tier_downlines'] }} / {{ $tierRule['min_tier_downlines'] }}</span></div>
          <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-info" style="width:{{ $dPct }}%"></div></div>
          <div class="d-flex justify-content-between small"><span>Packs sold <span class="text-body-secondary">(this period)</span></span><span class="fw-semibold">{{ $tierProgress['packs'] }} / {{ $tierRule['min_packs'] }}</span></div>
          <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:{{ $pPct }}%"></div></div>
          @if ($dPct >= 100 && $pPct >= 100)
            <div class="alert alert-success py-2 px-3 small mt-3 mb-0">✅ Qualifies for {{ \App\Models\User::tierLabelFor($nextTier) }} — will promote on next sale or Recalculate.</div>
          @endif
        @else
          <div class="text-secondary small">🎖️ Top rank reached ({{ $agent->tierLabel() }}).</div>
        @endif
      </div>

      <div class="card p-4 mb-3">
        <h6 class="fw-bold mb-3">Manage Agent</h6>
        <form method="POST" action="{{ route('manage.agents.update', $agent) }}">
          @csrf @method('PUT')
          <label class="form-label small fw-semibold">Tier</label>
          <select name="agent_tier" class="form-select form-select-sm mb-3">
            @foreach (\App\Http\Controllers\Manage\AgentController::TIERS as $k => $lbl)
              <option value="{{ $k }}" @selected($agent->agent_tier === $k)>{{ $lbl }}</option>
            @endforeach
          </select>
          <label class="form-label small fw-semibold">Status</label>
          <select name="status" class="form-select form-select-sm mb-3">
            @foreach (\App\Http\Controllers\Manage\AgentController::STATUSES as $k => $lbl)
              <option value="{{ $k }}" @selected($agent->status === $k)>{{ $lbl }}</option>
            @endforeach
          </select>
          <button class="btn btn-brand w-100">Save</button>
        </form>
      </div>

      <div class="card p-4 mb-3">
        <h6 class="fw-bold mb-3">Upline Chain</h6>
        @forelse ($upline as $u)
          <div class="d-flex align-items-center gap-2 py-1">
            <span class="badge text-bg-secondary" style="width:38px">L{{ $u->depth }}</span>
            <span class="small">{{ \App\Models\User::find($u->user_id)?->name ?? '—' }}</span>
          </div>
        @empty
          <div class="text-secondary small">Root agent — no upline.</div>
        @endforelse
      </div>

      <div class="card p-4">
        <h6 class="fw-bold mb-3">Direct Downlines ({{ $agent->referrals->count() }})</h6>
        @forelse ($agent->referrals as $d)
          <div class="d-flex align-items-center justify-content-between py-1">
            <a href="{{ route('manage.agents.show', $d) }}" class="small text-decoration-none">{{ $d->name }}</a>
            <span class="text-secondary small">RM {{ number_format((float) ($d->wallet->balance ?? 0), 2) }}</span>
          </div>
        @empty
          <div class="text-secondary small">No direct recruits.</div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
