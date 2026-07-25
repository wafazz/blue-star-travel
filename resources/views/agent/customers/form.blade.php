@extends('layouts.agent')
@section('title', $customer->exists ? 'Edit Customer' : 'Register Customer')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.customers.index') }}">‹</a>
    <div>
      <div class="t">{{ $customer->exists ? 'Edit Customer' : 'Register Customer' }}</div>
      <div class="sub">{{ $customer->exists ? $customer->name : 'Add someone to your customer list' }}</div>
    </div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ $customer->exists ? route('agent.customers.update', $customer) : route('agent.customers.store') }}" class="wrap">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <div class="card">
      <h3>Customer Details</h3>

      <label class="lbl">Name</label>
      <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="inp" placeholder="Name as per IC/passport" required>

      <label class="lbl">Phone No.</label>
      <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="inp" placeholder="01X-XXX XXXX" required>

      <label class="lbl">Email (optional)</label>
      <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="inp" placeholder="you@email.com">
    </div>

    <button class="btn" style="margin-bottom:20px">{{ $customer->exists ? 'Save Changes' : 'Register Customer' }}</button>
  </form>
@endsection
