@extends('layouts.admin')
@section('title', 'Company Profile')
@section('console', 'Management')
@section('heading', 'Company Profile')

@section('content')
  <form method="POST" action="{{ route('manage.company.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="row g-3" style="max-width:960px">
      <div class="col-lg-8">
        <div class="card p-4">
          <h6 class="fw-bold mb-3">Company Details</h6>
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label fw-semibold small">Trading Name *</label>
              <input type="text" name="name" value="{{ old('name', $company->name) }}" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold small">Legal Name</label>
              <input type="text" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Registration No.</label>
              <input type="text" name="registration_no" value="{{ old('registration_no', $company->registration_no) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Travel License No.</label>
              <input type="text" name="license_no" value="{{ old('license_no', $company->license_no) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Email</label>
              <input type="email" name="email" value="{{ old('email', $company->email) }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Phone</label>
              <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" class="form-control">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold small">Website</label>
              <input type="text" name="website" value="{{ old('website', $company->website) }}" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Currency *</label>
              <input type="text" name="currency" value="{{ old('currency', $company->currency ?? 'MYR') }}" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold small">Address</label>
              <input type="text" name="address" value="{{ old('address', $company->address) }}" class="form-control">
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold small">City</label>
              <input type="text" name="city" value="{{ old('city', $company->city) }}" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">State</label>
              <input type="text" name="state" value="{{ old('state', $company->state) }}" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Postcode</label>
              <input type="text" name="postcode" value="{{ old('postcode', $company->postcode) }}" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card p-4 mb-3">
          <h6 class="fw-bold mb-3">Logo</h6>
          @if ($company->logo)
            <img src="{{ asset('storage/' . $company->logo) }}" alt="logo" class="img-fluid rounded mb-2" style="max-height:120px">
          @else
            <div class="bg-body-secondary rounded d-flex align-items-center justify-content-center mb-2" style="height:120px">✈️</div>
          @endif
          <input type="file" name="logo" accept="image/*" class="form-control form-control-sm">
        </div>

        <div class="card p-4">
          <h6 class="fw-bold mb-3">Banking</h6>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Bank Name</label>
            <input type="text" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Account No.</label>
            <input type="text" name="bank_account_no" value="{{ old('bank_account_no', $company->bank_account_no) }}" class="form-control">
          </div>
          <div>
            <label class="form-label fw-semibold small">Account Name</label>
            <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $company->bank_account_name) }}" class="form-control">
          </div>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-brand">💾 Save Company Profile</button>
    </div>
  </form>
@endsection
