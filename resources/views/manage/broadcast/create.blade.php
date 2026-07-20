@extends('layouts.admin')
@section('title', 'Broadcast')
@section('console', 'Management')
@section('heading', 'Send Broadcast')

@section('content')
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">📢 Broadcast Notification</h6>
        <form method="POST" action="{{ route('manage.broadcast.send') }}">
          @csrf
          <label class="form-label small fw-semibold">Audience</label>
          <select name="audience" class="form-select mb-3">
            <option value="agent">All Agents</option>
            <option value="customer">All Customers</option>
            <option value="provider">All Providers</option>
          </select>
          <label class="form-label small fw-semibold">Title</label>
          <input type="text" name="title" class="form-control mb-3" placeholder="🎉 Raya Promo is live!" required>
          <label class="form-label small fw-semibold">Message</label>
          <textarea name="body" rows="3" class="form-control mb-3" placeholder="Details of the announcement…"></textarea>
          <label class="form-label small fw-semibold">Channels</label>
          <div class="d-flex gap-3 mb-3">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="channels[]" value="inapp" checked disabled><label class="form-check-label small">In-app</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="channels[]" value="email"><label class="form-check-label small">Email</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="channels[]" value="sms"><label class="form-check-label small">SMS</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp"><label class="form-check-label small">WhatsApp</label></div>
          </div>
          <input type="hidden" name="channels[]" value="inapp">
          <div class="alert alert-light border small text-secondary">In-app is always sent. Email/SMS/WhatsApp are logged stubs until vendors are connected.</div>
          <button class="btn btn-brand">Send Broadcast</button>
        </form>
      </div>
    </div>
  </div>
@endsection
