@extends('layouts.admin')
@section('title', 'Provider Dashboard')
@section('console', 'Provider')
@section('heading', 'Provider Dashboard')

@section('nav')
  <a class="nav-link active px-2 py-2" href="{{ route('provider.dashboard') }}">🏠 Dashboard</a>
  <a class="nav-link px-2 py-2" href="{{ route('provider.bookings.index') }}">📋 Incoming Bookings</a>
  <a class="nav-link px-2 py-2 disabled opacity-50" href="#">📅 Availability</a>
  <a class="nav-link px-2 py-2 disabled opacity-50" href="#">📄 Confirmation Documents</a>
@endsection

@section('content')
  <div class="alert alert-primary bg-primary bg-opacity-10 border-0 small">
    Phase 0 foundation — confirm / reject bookings and availability updates arrive in Phase 2.
  </div>
  <div class="row g-3">
    <div class="col-6 col-lg-3">
      <div class="card p-3"><div class="fs-4 fw-bold">6</div><div class="text-secondary small">Awaiting Response</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card p-3"><div class="fs-4 fw-bold">142</div><div class="text-secondary small">Confirmed (Mo)</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card p-3"><div class="fs-4 fw-bold">98%</div><div class="text-secondary small">Confirmation Rate</div></div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card p-3"><div class="fs-4 fw-bold">Active</div><div class="text-secondary small">Availability Status</div></div>
    </div>
  </div>
@endsection
