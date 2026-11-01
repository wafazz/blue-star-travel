@extends('layouts.admin')
@section('title', 'Change Password')
@section('console', 'Management')
@section('heading', 'Change Password')

@section('content')
  @if (session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if ($errors->any() && ! $errors->has('current_password') && ! $errors->has('password'))<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <div class="row g-4">
    <div class="col-lg-6">
      <form method="POST" action="{{ route('customer.password.update') }}" class="card p-4">
        @csrf
        @method('PUT')
        <h6 class="fw-bold mb-3">Change password</h6>
        @include('account.partials.password-fields', ['inputClass' => 'form-control', 'labelClass' => 'form-label small fw-semibold'])
        <button class="btn btn-brand">Update password</button>
      </form>
    </div>
  </div>
@endsection
