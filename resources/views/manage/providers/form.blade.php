@extends('layouts.admin')
@section('title', $provider->exists ? 'Edit Provider' : 'New Provider')
@section('console', 'Management')
@section('heading', $provider->exists ? 'Edit Provider' : 'New Provider')

@section('content')
  <form method="POST" action="{{ $provider->exists ? route('manage.providers.update', $provider) : route('manage.providers.store') }}">
    @csrf
    @if ($provider->exists) @method('PUT') @endif

    <div class="card p-4" style="max-width:760px">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label fw-semibold small">Provider Name *</label>
          <input type="text" name="name" value="{{ old('name', $provider->name) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold small">Type *</label>
          <select name="type" class="form-select" required>
            @foreach (\App\Models\Provider::TYPES as $k => $label)
              <option value="{{ $k }}" @selected(old('type', $provider->type) === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Contact Person</label>
          <input type="text" name="contact_person" value="{{ old('contact_person', $provider->contact_person) }}" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Phone</label>
          <input type="text" name="phone" value="{{ old('phone', $provider->phone) }}" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Email</label>
          <input type="email" name="email" value="{{ old('email', $provider->email) }}" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Status *</label>
          <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $provider->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $provider->status) === 'inactive')>Inactive</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold small">Address</label>
          <input type="text" name="address" value="{{ old('address', $provider->address) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold small">City</label>
          <input type="text" name="city" value="{{ old('city', $provider->city) }}" class="form-control">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold small">Notes</label>
          <textarea name="notes" rows="3" class="form-control">{{ old('notes', $provider->notes) }}</textarea>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-brand">💾 Save Provider</button>
        <a href="{{ route('manage.providers.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </form>
@endsection
