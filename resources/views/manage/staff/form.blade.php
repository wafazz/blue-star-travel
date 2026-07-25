@extends('layouts.admin')
@section('title', $staff->exists ? 'Edit Staff' : 'New Staff')
@section('console', 'Management')
@section('heading', $staff->exists ? 'Edit Staff' : 'New Staff')

@php $granted = old('permissions', $staff->permissions ?? []); @endphp

@section('content')
  <a href="{{ route('manage.staff.index') }}" class="btn btn-sm btn-link text-decoration-none px-0 mb-2">← Back to staff</a>

  <form method="POST" action="{{ $staff->exists ? route('manage.staff.update', $staff) : route('manage.staff.store') }}">
    @csrf
    @if ($staff->exists) @method('PUT') @endif

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card p-4">
          <h6 class="fw-bold mb-3">Account</h6>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Name *</label>
            <input type="text" name="name" value="{{ old('name', $staff->name) }}" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email *</label>
            <input type="email" name="email" value="{{ old('email', $staff->email) }}" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $staff->phone) }}" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Password {{ $staff->exists ? '(leave blank to keep)' : '*' }}</label>
            <input type="password" name="password" class="form-control" autocomplete="new-password" {{ $staff->exists ? '' : 'required' }}>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Role *</label>
              <select name="role" id="roleSelect" class="form-select" onchange="togglePerms()">
                @foreach (\App\Http\Controllers\Manage\StaffController::ROLES as $k => $lbl)
                  <option value="{{ $k }}" @selected(old('role', $staff->role) === $k)>{{ $lbl }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Status *</label>
              <select name="status" class="form-select">
                @foreach (['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'] as $k => $lbl)
                  <option value="{{ $k }}" @selected(old('status', $staff->status ?? 'active') === $k)>{{ $lbl }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card p-4" id="permsCard">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="fw-bold mb-0">Section Access</h6>
            <div>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAll(true)">All</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAll(false)">None</button>
            </div>
          </div>
          <div class="small text-secondary mb-3" id="permHint">Tick the back-office sections this admin may access. HQ Managers always have full access.</div>
          <div class="row g-3">
            @foreach (\App\Support\Permissions::GROUPS as $group => $abilities)
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <div class="fw-semibold small text-uppercase text-secondary mb-2" style="letter-spacing:.03em">{{ $group }}</div>
                  @foreach ($abilities as $key => $label)
                    <div class="form-check">
                      <input class="form-check-input perm-box" type="checkbox" name="permissions[]" value="{{ $key }}" id="perm_{{ $key }}" @checked(in_array($key, $granted))>
                      <label class="form-check-label small" for="perm_{{ $key }}">{{ $label }}</label>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-brand">💾 Save Staff</button>
      <a href="{{ route('manage.staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>

  <script>
    function togglePerms(){
      const isAdmin = document.getElementById('roleSelect').value === 'admin';
      document.getElementById('permsCard').style.opacity = isAdmin ? '1' : '.5';
      document.getElementById('permHint').textContent = isAdmin
        ? 'Tick the back-office sections this admin may access. HQ Managers always have full access.'
        : 'HQ Managers have full access — section limits below are ignored for this role.';
      document.querySelectorAll('.perm-box').forEach(b => b.disabled = !isAdmin);
    }
    function setAll(v){ document.querySelectorAll('.perm-box').forEach(b => { if (!b.disabled) b.checked = v; }); }
    togglePerms();
  </script>
@endsection
