@extends('layouts.admin')
@section('title', 'Staff & Access')
@section('console', 'Management')
@section('heading', 'Staff & Access Control')

@php
  $roleBadge = ['super_admin' => 'dark', 'hq' => 'primary', 'admin' => 'info'];
  $statusBadge = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger'];
@endphp

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="small text-secondary">HQ &amp; Super Admin have full access. Admin staff see only the sections granted to them below.</div>
    <a href="{{ route('manage.staff.create') }}" class="btn btn-brand btn-sm">＋ New Staff</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Staff</th><th>Role</th><th>Access</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          @foreach ($staff as $member)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px">{{ $member->initials() }}</span>
                  <div><div class="fw-semibold">{{ $member->name }}</div><div class="text-secondary small">{{ $member->email }}</div></div>
                </div>
              </td>
              <td><span class="badge text-bg-{{ $roleBadge[$member->role] ?? 'secondary' }}">{{ \App\Http\Controllers\Manage\StaffController::ROLES[$member->role] ?? ucfirst(str_replace('_',' ',$member->role)) }}</span></td>
              <td class="small">
                @if (in_array($member->role, ['super_admin', 'hq']))
                  <span class="text-success">Full access</span>
                @else
                  {{ count($member->permissions ?? []) }} of {{ count(\App\Support\Permissions::keys()) }} sections
                @endif
              </td>
              <td><span class="badge text-bg-{{ $statusBadge[$member->status] ?? 'secondary' }}">{{ ucfirst($member->status) }}</span></td>
              <td class="text-end">
                @if (in_array($member->role, ['hq', 'admin']))
                  <a href="{{ route('manage.staff.edit', $member) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                  @if ($member->id !== auth()->id())
                    <form action="{{ route('manage.staff.destroy', $member) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this staff account?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  @endif
                @else
                  <span class="badge text-bg-light text-secondary">Owner</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $staff->links() }}</div>
@endsection
