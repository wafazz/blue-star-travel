@extends('layouts.admin')
@section('title', $customer->exists ? 'Edit Customer' : 'New Customer')
@section('console', 'Management')
@section('heading', $customer->exists ? 'Edit Customer' : 'New Customer')

@section('content')
  <form method="POST" action="{{ $customer->exists ? route('manage.customers.update', $customer) : route('manage.customers.store') }}">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <div class="row g-3" style="max-width:960px">
      <div class="col-lg-7">
        <div class="card p-4 h-100">
          <h6 class="fw-bold mb-3">Personal Details</h6>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold small">Full Name *</label>
              <input type="text" name="name" value="{{ old('name', $customer->name) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Gender</label>
              <select name="gender" class="form-select">
                <option value="">—</option>
                @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $v)
                  <option value="{{ $k }}" @selected(old('gender', $customer->gender) === $k)>{{ $v }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Email</label>
              <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Phone</label>
              <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">IC / Passport No.</label>
              <input type="text" name="ic_passport_no" value="{{ old('ic_passport_no', $customer->ic_passport_no) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Passport Expiry</label>
              <input type="date" name="passport_expiry" value="{{ old('passport_expiry', optional($customer->passport_expiry)->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Nationality</label>
              <input type="text" name="nationality" value="{{ old('nationality', $customer->nationality ?? 'Malaysian') }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Date of Birth</label>
              <input type="date" name="dob" value="{{ old('dob', optional($customer->dob)->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Address</label>
              <input type="text" name="address" value="{{ old('address', $customer->address) }}" class="form-control">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold small">City</label>
              <input type="text" name="city" value="{{ old('city', $customer->city) }}" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">State</label>
              <input type="text" name="state" value="{{ old('state', $customer->state) }}" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Postcode</label>
              <input type="text" name="postcode" value="{{ old('postcode', $customer->postcode) }}" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card p-4 mb-3">
          <h6 class="fw-bold mb-3">Assignment &amp; Loyalty</h6>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Owning Agent</label>
            <select name="agent_id" class="form-select">
              <option value="">— Unassigned —</option>
              @foreach ($agents as $a)
                <option value="{{ $a->id }}" @selected((string) old('agent_id', $customer->agent_id) === (string) $a->id)>{{ $a->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Loyalty Points</label>
            <input type="number" name="loyalty_points" value="{{ old('loyalty_points', $customer->loyalty_points ?? 0) }}" class="form-control" min="0">
          </div>
          <div>
            <label class="form-label fw-semibold small">Status *</label>
            <select name="status" class="form-select" required>
              <option value="active" @selected(old('status', $customer->status ?? 'active') === 'active')>Active</option>
              <option value="inactive" @selected(old('status', $customer->status) === 'inactive')>Inactive</option>
            </select>
          </div>
        </div>

        <div class="card p-4">
          <h6 class="fw-bold mb-3">Emergency Contact</h6>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Name</label>
            <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $customer->emergency_contact_name) }}" class="form-control">
          </div>
          <div>
            <label class="form-label fw-semibold small">Phone</label>
            <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $customer->emergency_contact_phone) }}" class="form-control">
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card p-4">
          <label class="form-label fw-semibold small">Notes</label>
          <textarea name="notes" rows="2" class="form-control">{{ old('notes', $customer->notes) }}</textarea>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-brand">💾 Save Customer</button>
      <a href="{{ route('manage.customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
@endsection
