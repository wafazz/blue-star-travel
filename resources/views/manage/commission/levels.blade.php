@extends('layouts.admin')
@section('title', 'Commission Levels')
@section('console', 'Management')
@section('heading', 'Commission Configuration')

@section('content')
  <div class="alert alert-primary bg-primary bg-opacity-10 border-0 small">
    The number of <strong>active levels below</strong> sets the cascade depth — how many upline layers earn on each sale.
    Currently paying <strong>{{ $levels->where('active', true)->count() }}</strong> level(s).
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Payout Levels</h6>
        @forelse ($levels as $level)
          <div class="d-flex align-items-center gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
            <span class="badge text-bg-primary" style="width:42px">L{{ $level->level }}</span>
            <form method="POST" action="{{ route('manage.commission.levels.update', $level) }}" class="d-flex align-items-center gap-2 flex-fill flex-wrap">
              @csrf @method('PUT')
              <input type="text" name="label" value="{{ $level->label }}" class="form-control form-control-sm" placeholder="Label" style="max-width:150px">
              <div class="input-group input-group-sm" style="max-width:120px"><input type="number" name="percent" value="{{ $level->percent }}" step="0.01" min="0" max="100" class="form-control"><span class="input-group-text">%</span></div>
              <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($level->active)><label class="form-check-label small text-secondary">Active</label></div>
              <button class="btn btn-sm btn-outline-primary ms-auto">Save</button>
            </form>
            <form method="POST" action="{{ route('manage.commission.levels.destroy', $level) }}" onsubmit="return confirm('Remove level {{ $level->level }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑</button></form>
          </div>
        @empty
          <div class="text-center text-secondary py-4">No levels configured. Add the first level.</div>
        @endforelse
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Add Level</h6>
        <form method="POST" action="{{ route('manage.commission.levels.store') }}">
          @csrf
          <label class="form-label small fw-semibold">Level #</label>
          <input type="number" name="level" min="1" value="{{ ($levels->max('level') ?? 0) + 1 }}" class="form-control mb-2" required>
          <label class="form-label small fw-semibold">Label</label>
          <input type="text" name="label" class="form-control mb-2" placeholder="e.g. Level 4">
          <label class="form-label small fw-semibold">Percent</label>
          <div class="input-group mb-3"><input type="number" name="percent" step="0.01" min="0" max="100" class="form-control" required><span class="input-group-text">%</span></div>
          <button class="btn btn-brand w-100">＋ Add Level</button>
        </form>
      </div>

      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-1">🏢 Default HQ Commission</h6>
        <div class="small text-secondary mb-3">The company's own cut on <strong>every</strong> agent sale, on top of the upline cascade. A package can override this with its own HQ level.</div>
        <form method="POST" action="{{ route('manage.commission.hq') }}">
          @csrf
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="active" id="hqActive" value="1" @checked($hq['active'])>
            <label class="form-check-label small fw-semibold" for="hqActive">Enable HQ commission</label>
          </div>
          <label class="form-label small fw-semibold">Payout Type</label>
          <select name="rate_type" id="hqRate" class="form-select form-select-sm mb-3" onchange="hqUnit()">
            <option value="percent" @selected($hq['rate_type'] === 'percent')>Percentage (%)</option>
            <option value="fixed" @selected($hq['rate_type'] === 'fixed')>Fixed (RM)</option>
          </select>
          <div class="row g-2">
            @foreach (['adult' => 'Adult', 'child' => 'Child', 'senior' => 'Senior', 'infant' => 'Infant'] as $k => $lbl)
              <div class="col-6">
                <label class="form-label small mb-1">{{ $lbl }}</label>
                <div class="input-group input-group-sm"><span class="input-group-text hq-unit">{{ $hq['rate_type'] === 'fixed' ? 'RM' : '%' }}</span><input type="number" step="0.01" min="0" name="{{ $k }}_value" value="{{ $hq[$k.'_value'] }}" class="form-control"></div>
              </div>
            @endforeach
          </div>
          <button class="btn btn-brand w-100 mt-3">Save HQ Commission</button>
        </form>
      </div>

      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Recruitment Depth Cap</h6>
        <form method="POST" action="{{ route('manage.commission.settings') }}">
          @csrf
          <label class="form-label small fw-semibold">Max recruitment depth (0 = unlimited)</label>
          <input type="number" name="agent_max_depth" min="0" max="50" value="{{ $maxDepth }}" class="form-control mb-2">
          <div class="form-text mb-2">Caps how deep the network can recruit. Separate from payout depth — you can recruit deeper than you pay.</div>
          <button class="btn btn-outline-primary w-100">Save Cap</button>
        </form>
      </div>
    </div>
  </div>
  <script>
    function hqUnit(){
      const u = document.getElementById('hqRate').value === 'fixed' ? 'RM' : '%';
      document.querySelectorAll('.hq-unit').forEach(el => el.textContent = u);
    }
  </script>
@endsection
