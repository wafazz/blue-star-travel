@extends('layouts.admin')
@section('title', 'New Agent')
@section('console', 'Management')
@section('heading', 'New Agent')

@section('content')
  <a href="{{ route('manage.agents.index') }}" class="btn btn-sm btn-link text-decoration-none px-0 mb-2">← Back to agents</a>

  <form method="POST" action="{{ route('manage.agents.store') }}">
    @csrf

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card p-4">
          <h6 class="fw-bold mb-3">Account</h6>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email *</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Password *</label>
            <input type="password" name="password" class="form-control" autocomplete="new-password" required>
            <div class="form-text small">Minimum 8 characters. Share it with the agent — they can sign in immediately once active.</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card p-4">
          <h6 class="fw-bold mb-3">Network Placement</h6>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Agent Code</label>
            <input type="text" class="form-control" value="{{ $nextCode }}" disabled>
            <div class="form-text small">Assigned automatically on save.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Upline Agent Code</label>
            <input type="text" name="upline" value="{{ old('upline') }}" class="form-control" placeholder="e.g. BT-AG001 — leave blank for a root agent">
            <div class="form-text small">The agent this recruit sits under. Commission cascades up this chain. Blank = a root agent with no upline.</div>
          </div>

          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Tier *</label>
              <select name="agent_tier" class="form-select">
                @foreach (\App\Http\Controllers\Manage\AgentController::TIERS as $k => $lbl)
                  <option value="{{ $k }}" @selected(old('agent_tier', 'agent') === $k)>{{ \App\Models\User::TIER_ICONS[$k] ?? '' }} {{ $lbl }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Status *</label>
              <select name="status" class="form-select">
                @foreach (\App\Http\Controllers\Manage\AgentController::STATUSES as $k => $lbl)
                  <option value="{{ $k }}" @selected(old('status', 'active') === $k)>{{ $lbl }}</option>
                @endforeach
              </select>
              <div class="form-text small">Only <strong>Active</strong> agents can sign in.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-brand">💾 Create Agent</button>
      <a href="{{ route('manage.agents.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
@endsection
