@extends('layouts.admin')
@section('title', 'My Business Card')
@section('console', 'Management')
@section('heading', 'My Business Card')

@section('content')
  @php $shareUrl = route('card.staff', $user); @endphp

  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <div class="row g-4">
    <div class="col-lg-7">
      @include('partials.business-card', ['user' => $user])
    </div>

    <div class="col-lg-5">
      <div class="card p-4 mb-3">
        <h6 class="fw-bold mb-2">Share</h6>
        <p class="small text-secondary mb-2">Anyone with this link can see your card and save your contact.</p>
        <div class="input-group mb-2">
          <input id="cardLink" type="text" class="form-control form-control-sm" readonly value="{{ $shareUrl }}">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyCardLink(this)">Copy</button>
        </div>
        <div class="d-flex flex-wrap gap-2">
          @include('partials.share-card-button', ['shareUrl' => $shareUrl, 'shareName' => $user->name, 'class' => 'btn btn-sm btn-brand'])
          <a href="{{ route('card.staff.vcard', $user) }}" class="btn btn-sm btn-outline-primary">Download contact (.vcf)</a>
        </div>
      </div>

      <form method="POST" action="{{ route('manage.card.update') }}" class="card p-4">
        @csrf
        @method('PUT')
        <h6 class="fw-bold mb-3">Card details</h6>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Name</label>
          <input type="text" class="form-control" value="{{ $user->name }}" disabled>
          <div class="form-text">Changed by HQ.</div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Title / Designation</label>
          <input type="text" name="title" value="{{ old('title', $user->title) }}" class="form-control" maxlength="80" placeholder="e.g. Sales Executive">
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Phone No.</label>
          <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" maxlength="30" placeholder="01X-XXX XXXX">
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Email</label>
          <input type="email" class="form-control" value="{{ $user->email }}" disabled>
          <div class="form-text">Changed by HQ — it is also your login.</div>
        </div>

        <button class="btn btn-brand">Save card</button>
      </form>
    </div>
  </div>

  <script>
    function copyCardLink(btn){
      const f = document.getElementById('cardLink');
      const done = () => { btn.textContent = 'Copied ✓'; setTimeout(() => btn.textContent = 'Copy', 1800); };
      f.select(); f.setSelectionRange(0, 99999);
      if (navigator.clipboard) {
        navigator.clipboard.writeText(f.value).then(done, () => { document.execCommand('copy'); done(); });
      } else {
        document.execCommand('copy'); done();
      }
    }
  </script>
@endsection
