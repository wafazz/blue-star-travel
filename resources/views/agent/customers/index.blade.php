@extends('layouts.agent')
@section('title', 'My Customers')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">My Customers</div><div class="sub">{{ $customers->total() }} registered</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="wrap">
    <a class="btn" href="{{ route('agent.customers.create') }}" style="text-align:center;text-decoration:none;margin-bottom:14px">➕ Register Customer</a>

    @forelse ($customers as $c)
      <a class="brow" href="{{ route('agent.customers.edit', $c) }}">
        <div>
          <div class="n">{{ $c->name }}</div>
          <div class="m">{{ $c->phone }}{{ $c->email ? ' · ' . $c->email : '' }}</div>
        </div>
        <span class="badge b-{{ $c->bookings_count ? 'success' : 'secondary' }}">
          {{ $c->bookings_count }} {{ Str::plural('booking', $c->bookings_count) }}
        </span>
      </a>
    @empty
      <div class="empty">No customers yet.<br>Register one to start booking.</div>
    @endforelse

    <div style="padding:10px 0">{{ $customers->links() }}</div>
  </div>
@endsection
