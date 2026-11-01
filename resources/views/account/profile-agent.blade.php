@extends('layouts.agent')
@section('title', 'My Profile')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.network') }}">‹</a>
    <div><div class="t">My Profile</div><div class="sub">Your name, phone and title</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">⚠️ {{ $errors->first() }}</div>@endif

  <div class="wrap">
    <form method="POST" action="{{ route('customer.profile.update') }}">
      @csrf
      @method('PUT')

      <div class="card">
        <h3>Details</h3>

        <label class="lbl">Full name</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="inp" maxlength="120" required>

        <label class="lbl">Phone No.</label>
        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="inp" maxlength="30" placeholder="01X-XXX XXXX">

        <label class="lbl">Title / Designation</label>
        <input type="text" name="title" value="{{ old('title', $user->title) }}" class="inp" maxlength="80" placeholder="e.g. Sales Executive">
      </div>

      <div class="card">
        <h3>Account</h3>

        <label class="lbl">Email (login)</label>
        <input type="email" value="{{ $user->email }}" class="inp" disabled>
        <div class="m" style="font-size:11px;margin:4px 0 12px">Changed by HQ only.</div>

        <label class="lbl">Member ID</label>
        <input type="text" value="{{ $user->agent_code }}" class="inp" disabled>
      </div>

      <div style="padding-bottom:20px"><button class="btn">Save Profile</button></div>
    </form>
  </div>
@endsection
