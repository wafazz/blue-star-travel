@extends('layouts.admin')
@section('title', 'My Profile')
@section('console', 'Management')
@section('heading', 'My Profile')

@section('content')
  @if (session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <div class="row g-4">
    <div class="col-lg-6">
      <form method="POST" action="{{ route('customer.profile.update') }}" class="card p-4">
        @csrf
        @method('PUT')
        <h6 class="fw-bold mb-3">Details</h6>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Full name</label>
          <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" maxlength="120" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Phone No.</label>
          <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" maxlength="30" placeholder="01X-XXX XXXX">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Title / Designation</label>
          <input type="text" name="title" value="{{ old('title', $user->title) }}" class="form-control" maxlength="80" placeholder="e.g. Sales Executive">
        </div>

        <h6 class="fw-bold mt-4 mb-3">Account</h6>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email (login)</label>
          <input type="email" value="{{ $user->email }}" class="form-control" disabled>
          <div class="form-text">Changed by HQ only.</div>
        </div>

        <button class="btn btn-brand">Save profile</button>
      </form>
    </div>
  </div>
@endsection
