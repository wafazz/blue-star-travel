@extends('layouts.admin')
@section('title', 'Agents')
@section('console', 'Management')
@section('heading', 'Agents — MLM Network')

@php
  $tierBadge = ['agent' => 'secondary', 'assistant_mentor' => 'warning', 'mentor' => 'info'];
  $statusBadge = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger'];
@endphp

@section('content')
  <div class="d-flex justify-content-end mb-3">
    <a href="{{ route('manage.agents.create') }}" class="btn btn-brand">➕ New Agent</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-primary">{{ $kpis['total'] }}</div><div class="text-secondary small">Total Agents</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-success">{{ $kpis['active'] }}</div><div class="text-secondary small">Active</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-dark">RM {{ number_format($kpis['wallet_total'], 2) }}</div><div class="text-secondary small">Wallet Balances</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">RM {{ number_format($kpis['pending_comm'], 2) }}</div><div class="text-secondary small">Pending Commission</div></div></div>
  </div>

  @if ($kpis['pending'])
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 px-3">
      <span class="small">⏳ <strong>{{ $kpis['pending'] }}</strong> agent {{ Str::plural('application', $kpis['pending']) }} awaiting approval — a pending account cannot sign in until you set it to Active.</span>
      <a href="{{ route('manage.agents.index', ['status' => 'pending']) }}" class="btn btn-sm btn-warning">Review</a>
    </div>
  @endif

  <div class="card p-3 p-lg-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h6 class="fw-bold mb-0">🏆 Tier Promotion Rules</h6>
      <form method="POST" action="{{ route('manage.agents.recalculate') }}" onsubmit="return confirm('Re-evaluate every agent against the current rules now?')">
        @csrf
        <button class="btn btn-sm btn-outline-primary">🔄 Recalculate Tiers</button>
      </form>
    </div>
    <div class="small text-secondary mb-2">An agent is auto-promoted to the highest tier whose thresholds they meet — needing <strong>both</strong> the required number of downlines at the rank below <strong>and</strong> packs sold. Runs on every paid sale; use Recalculate for downline-driven promotions. Promote-only (never auto-demotes).</div>
    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 small py-2 px-3 mb-3">📅 Qualification period: <strong>{{ $period['label'] }}</strong> — packs counted within each half-year (Jan–Jun / Jul–Dec). At each period rollover every agent is <strong>re-qualified</strong>: ranks not re-earned in the period are <strong>demoted</strong> (runs automatically; Recalculate applies a pending rollover).</div>
    <form method="POST" action="{{ route('manage.agents.tier-rules') }}">
      @csrf
      <div class="row g-3">
        @foreach (['assistant_mentor', 'mentor'] as $tier)
          @php $reqTier = \App\Services\TierService::REQUIRES_TIER[$tier]; @endphp
          <div class="col-md-6">
            <div class="border rounded-3 p-3">
              <div class="fw-semibold mb-2">{{ \App\Models\User::TIER_ICONS[$tier] }} Promote to {{ \App\Models\User::tierLabelFor($tier) }}</div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small mb-1">Min. {{ \App\Models\User::tierLabelFor($reqTier) }}s in downline</label>
                  <input type="number" min="0" name="rules[{{ $tier }}][min_tier_downlines]" value="{{ $tierRules[$tier]['min_tier_downlines'] }}" class="form-control form-control-sm">
                </div>
                <div class="col-6">
                  <label class="form-label small mb-1">Min. packs sold</label>
                  <input type="number" min="0" name="rules[{{ $tier }}][min_packs]" value="{{ $tierRules[$tier]['min_packs'] }}" class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <button class="btn btn-brand btn-sm mt-3">Save Rules</button>
    </form>
  </div>

  <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, email, code, phone…" style="min-width:240px">
    <select name="tier" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any tier</option>
      @foreach (\App\Http\Controllers\Manage\AgentController::TIERS as $k => $lbl)
        <option value="{{ $k }}" @selected(request('tier') === $k)>{{ $lbl }}</option>
      @endforeach
    </select>
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (\App\Http\Controllers\Manage\AgentController::STATUSES as $k => $lbl)
        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $lbl }}</option>
      @endforeach
    </select>
    <button class="btn btn-sm btn-outline-secondary">Search</button>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Agent</th><th>Code</th><th>Tier</th><th>Upline</th>
            <th class="text-center">Direct Downlines</th><th class="text-end">Lifetime Sales</th>
            <th class="text-end">Wallet</th><th>Status</th><th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($agents as $agent)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px">{{ $agent->initials() }}</span>
                  <div><div class="fw-semibold">{{ $agent->name }}</div><div class="text-secondary small">{{ $agent->email }}</div></div>
                </div>
              </td>
              <td><span class="badge text-bg-light border">{{ $agent->agent_code ?: '—' }}</span></td>
              <td><span class="badge text-bg-{{ $tierBadge[$agent->agent_tier] ?? 'secondary' }}">{{ $agent->tierLabel() }}</span></td>
              <td class="small">{{ $agent->referrer?->name ?? '—' }}</td>
              <td class="text-center">{{ $agent->direct_downlines }}</td>
              <td class="text-end small">RM {{ number_format((float) $agent->sales_total, 2) }}</td>
              <td class="text-end small fw-semibold">RM {{ number_format((float) ($agent->wallet->balance ?? 0), 2) }}</td>
              <td><span class="badge text-bg-{{ $statusBadge[$agent->status] ?? 'secondary' }}">{{ ucfirst($agent->status) }}</span></td>
              <td class="text-end"><a href="{{ route('manage.agents.show', $agent) }}" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-secondary py-4">No agents yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $agents->links() }}</div>
@endsection
