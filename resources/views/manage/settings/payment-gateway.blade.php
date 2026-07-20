@extends('layouts.admin')
@section('title', 'Payment Gateway')
@section('console', 'Management')
@section('heading', 'Payment Gateway Configuration')

@section('content')
  <form method="POST" action="{{ route('manage.payment-gateway.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-lg-7">

        <div class="card p-3 p-lg-4 mb-3">
          <h6 class="fw-bold mb-1">Active Gateway</h6>
          <p class="text-secondary small">Which provider collects online payments from customers and agents.</p>

          @foreach ($drivers as $key => $label)
            <label class="d-flex align-items-start gap-3 p-3 border rounded-3 mb-2 {{ $activeDriver === $key ? 'border-primary bg-primary bg-opacity-10' : '' }}" style="cursor:pointer">
              <input type="radio" name="driver" value="{{ $key }}" class="form-check-input mt-1" @checked($activeDriver === $key)>
              <span>
                <span class="fw-semibold d-block">{{ $label }}</span>
                <span class="text-secondary small">
                  @if ($key === 'sandbox')
                    Simulated bank screen for testing. Never credits a booking on its own — payments land pending staff verification, and the screen is disabled in production.
                  @else
                    Live FPX &amp; online banking. Bookings are credited only after Billplz confirms payment server-to-server.
                  @endif
                </span>
              </span>
            </label>
          @endforeach
        </div>

        <div class="card p-3 p-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="fw-bold mb-0">Billplz Credentials</h6>
            <span class="badge text-bg-{{ ($billplz['sandbox'] ?? true) ? 'warning' : 'success' }}">{{ ($billplz['sandbox'] ?? true) ? 'Sandbox' : 'Live' }}</span>
          </div>
          <p class="text-secondary small">
            From your Billplz dashboard → <strong>Settings → Keys &amp; Integration</strong>.
            Secrets are encrypted before they are stored and are never shown again.
          </p>

          <label class="form-label small fw-semibold">API Secret Key</label>
          <input type="password" name="billplz_key" class="form-control mb-1" autocomplete="new-password"
                 placeholder="{{ $billplz['key'] ? 'Saved — leave blank to keep ' . $billplz['key'] : 'Paste your API secret key' }}">
          <div class="form-text mb-3">
            @if ($billplz['key'])
              Currently set{{ $fromEnv['key'] ? ' from .env' : '' }}. Type a new value to replace it.
            @else
              Not set yet.
            @endif
          </div>

          <label class="form-label small fw-semibold">X-Signature Key</label>
          <input type="password" name="billplz_x_signature" class="form-control mb-1" autocomplete="new-password"
                 placeholder="{{ $billplz['x_signature'] ? 'Saved — leave blank to keep ' . $billplz['x_signature'] : 'Paste your X-Signature key' }}">
          <div class="form-text mb-3">
            @if ($billplz['x_signature'])
              Currently set{{ $fromEnv['x_signature'] ? ' from .env' : '' }}. Type a new value to replace it.
            @else
              Not set yet — without it every payment callback is rejected.
            @endif
          </div>

          <label class="form-label small fw-semibold">Collection ID</label>
          <input type="text" name="billplz_collection_id" value="{{ old('billplz_collection_id', $billplz['collection_id']) }}"
                 class="form-control mb-3" placeholder="e.g. inbmmepb">

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="billplz_sandbox" value="1" id="sbx" @checked($billplz['sandbox'])>
            <label class="form-check-label" for="sbx">
              Use Billplz <strong>sandbox</strong> (billplz-sandbox.com)
              <span class="d-block text-secondary small">Turn this off only when you are ready to take real money.</span>
            </label>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary">Save Settings</button>
            <button class="btn btn-outline-secondary" formaction="{{ route('manage.payment-gateway.test') }}" formmethod="POST" formnovalidate>
              Test Connection
            </button>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card p-3 p-lg-4 mb-3">
          <h6 class="fw-bold mb-3">Billplz Callback URLs</h6>
          <p class="text-secondary small">Sent automatically with every bill — nothing to configure in the Billplz dashboard. Shown here for reference.</p>

          <label class="form-label small fw-semibold">Webhook (server-to-server)</label>
          <input type="text" class="form-control form-control-sm mb-3 bg-body-secondary" value="{{ $webhookUrl }}" readonly onclick="this.select()">

          <label class="form-label small fw-semibold">Return URL (customer redirect)</label>
          <input type="text" class="form-control form-control-sm bg-body-secondary" value="{{ $returnUrl }}" readonly onclick="this.select()">
        </div>

        <div class="card p-3 p-lg-4">
          <h6 class="fw-bold mb-3">How payments are confirmed</h6>
          <ul class="small text-secondary mb-0 ps-3" style="line-height:1.7">
            <li>Billplz signs every callback; an unsigned or tampered one is rejected outright.</li>
            <li>After the signature checks out, we <strong>ask Billplz again</strong> what happened — the posted amount is never trusted.</li>
            <li>A booking is marked paid (and commission generated) only when Billplz confirms the payment and the amount covers it.</li>
            <li>A short payment is held for manual review instead of being credited.</li>
            <li>Repeated callbacks are safe — they never double-credit.</li>
          </ul>
        </div>
      </div>
    </div>
  </form>
@endsection
