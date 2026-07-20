@extends('layouts.customer')
@section('title', 'Profile')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('customer.dashboard') }}">‹</a>
    <div><div class="t">Passport &amp; Profile</div><div class="sub">Keep your travel details up to date</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">⚠️ {{ $errors->first() }}</div>@endif

  <div class="wrap">
    <form method="POST" action="{{ route('customer.profile.update') }}">
      @csrf
      @method('PUT')

      <div class="card">
        <h3>Personal</h3>
        <label class="lbl">Full name (as per passport)</label>
        <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="inp" required>
        <label class="lbl">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="inp" required>
        <div class="row2">
          <div>
            <label class="lbl">Date of birth</label>
            <input type="date" name="dob" value="{{ old('dob', optional($customer->dob)->format('Y-m-d')) }}" class="inp">
          </div>
          <div>
            <label class="lbl">Gender</label>
            <select name="gender" class="inp">
              <option value="">—</option>
              <option value="male" @selected($customer->gender === 'male')>Male</option>
              <option value="female" @selected($customer->gender === 'female')>Female</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>🛂 Passport / IC</h3>
        <label class="lbl">IC / Passport number</label>
        <input type="text" name="ic_passport_no" value="{{ old('ic_passport_no', $customer->ic_passport_no) }}" class="inp">
        <div class="row2">
          <div>
            <label class="lbl">Passport expiry</label>
            <input type="date" name="passport_expiry" value="{{ old('passport_expiry', optional($customer->passport_expiry)->format('Y-m-d')) }}" class="inp">
          </div>
          <div>
            <label class="lbl">Nationality</label>
            <input type="text" name="nationality" value="{{ old('nationality', $customer->nationality) }}" class="inp">
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Address</h3>
        <label class="lbl">Address</label>
        <input type="text" name="address" value="{{ old('address', $customer->address) }}" class="inp">
        <div class="row2">
          <div>
            <label class="lbl">Postcode</label>
            <input type="text" name="postcode" value="{{ old('postcode', $customer->postcode) }}" class="inp">
          </div>
          <div>
            <label class="lbl">City</label>
            <input type="text" name="city" value="{{ old('city', $customer->city) }}" class="inp">
          </div>
        </div>
        <div class="row2">
          <div>
            <label class="lbl">State</label>
            <input type="text" name="state" value="{{ old('state', $customer->state) }}" class="inp">
          </div>
          <div>
            <label class="lbl">Country</label>
            <input type="text" name="country" value="{{ old('country', $customer->country) }}" class="inp">
          </div>
        </div>
      </div>

      <div class="card">
        <h3>🚨 Emergency Contact</h3>
        <label class="lbl">Contact name</label>
        <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $customer->emergency_contact_name) }}" class="inp">
        <label class="lbl">Contact phone</label>
        <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $customer->emergency_contact_phone) }}" class="inp">
      </div>

      <div style="padding-bottom:20px"><button class="btn">Save Profile</button></div>
    </form>
  </div>
@endsection
