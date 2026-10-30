@extends('layouts.admin')
@section('title', 'AI Assistant')
@section('console', 'Management')
@section('heading', 'AI Travel Assistant')

@section('content')
  <div class="row g-3">
    <div class="col-lg-7">

      <form method="POST" action="{{ route('manage.ai-assistant.update') }}">
        @csrf
        @method('PUT')

        <div class="card p-3 p-lg-4 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="fw-bold mb-0">Assistant Status</h6>
            <span class="badge text-bg-{{ $enabled ? 'success' : 'secondary' }}">{{ $enabled ? 'On' : 'Off' }}</span>
          </div>
          <p class="text-secondary small">Turn the assistant on or off for visitors and customers. Off shows a friendly "not available" message and makes no AI calls.</p>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="ai-enabled" @checked($enabled)>
            <label class="form-check-label fw-semibold" for="ai-enabled">Assistant is on</label>
          </div>
        </div>

        <div class="card p-3 p-lg-4 mb-3">
          <h6 class="fw-bold mb-1">Primary Provider</h6>
          <p class="text-secondary small">Tried first. If it fails or hits its quota, the other providers are tried in the order Anthropic → OpenAI → Gemini.</p>
          <select name="primary" class="form-select" style="max-width:320px">
            @foreach ($providers as $key => $p)
              <option value="{{ $key }}" @selected($primary === $key)>{{ $p['label'] }}</option>
            @endforeach
          </select>
        </div>

        @foreach ($providers as $key => $p)
          <div class="card p-3 p-lg-4 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <h6 class="fw-bold mb-0">{{ $p['label'] }}</h6>
              <span class="badge text-bg-{{ $p['ready'] ? 'success' : 'warning' }}">{{ $p['ready'] ? 'Ready' : 'Not ready' }}</span>
            </div>
            <p class="text-secondary small mb-3">
              Key source: <strong>{{ $p['key_source'] === 'settings' ? 'saved here (encrypted)' : ($p['key_source'] === 'env' ? '.env file' : 'not set') }}</strong>
            </p>

            <label class="form-label small fw-semibold">Model</label>
            @php
              $current = old("model.$key", $p['model']);
              $options = $p['model_options'];
              if ($current && $current !== \App\Http\Controllers\Manage\AiAssistantController::OTHER_MODEL && ! in_array($current, $options, true)) {
                  array_unshift($options, $current); // keep a saved ID that is not in the list
              }
            @endphp
            <select name="model[{{ $key }}]" class="form-select mb-2" aria-label="{{ $p['label'] }} model">
              <option value="">— Choose a model —</option>
              @foreach ($options as $option)
                <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
              @endforeach
              <option value="{{ \App\Http\Controllers\Manage\AiAssistantController::OTHER_MODEL }}" @selected($current === \App\Http\Controllers\Manage\AiAssistantController::OTHER_MODEL)>Other (type the model ID below)</option>
            </select>
            {{-- Standalone form (see below): a button inside this form cannot submit another form. --}}
            <button type="submit" form="refresh-{{ $key }}" class="btn btn-sm btn-outline-secondary mb-2">Check for new models</button>
            <div class="form-text mb-1">Adds any new chat models from {{ $p['label'] }} to the list above. It never changes the model in use.</div>
            <input type="text" name="model_other[{{ $key }}]" value="{{ old("model_other.$key") }}"
                   class="form-control mb-1" placeholder="Used only when Other is selected" maxlength="100">
            <div class="form-text mb-3">
              @if ($key === 'anthropic')
                Default <code>claude-opus-5-5</code>. Change only when you deliberately move to another Claude model.
              @elseif ($key === 'openai')
                No default is set. Check the current model ID on OpenAI's pricing page before you turn the assistant on.
              @else
                Default <code>gemini-3.6-flash</code>. Check the current model ID in Google AI Studio if requests fail with "model not found".
              @endif
            </div>

            <label class="form-label small fw-semibold">API key</label>
            <input type="password" name="key[{{ $key }}]" class="form-control mb-1" autocomplete="new-password"
                   placeholder="{{ $p['masked_key'] ? 'Saved (' . $p['masked_key'] . ') — leave blank to keep' : 'Paste the API key' }}">
            <div class="form-text mb-3">Stored encrypted. It is never shown again after saving.</div>

            <label class="form-label small fw-semibold">Price per 1M tokens (for the usage summary)</label>
            <div class="row g-2">
              <div class="col-sm-6">
                <input type="number" step="0.0001" min="0" name="price_in[{{ $key }}]" value="{{ old("price_in.$key", $p['price_in']) }}"
                       class="form-control" placeholder="Input price">
              </div>
              <div class="col-sm-6">
                <input type="number" step="0.0001" min="0" name="price_out[{{ $key }}]" value="{{ old("price_out.$key", $p['price_out']) }}"
                       class="form-control" placeholder="Output price">
              </div>
            </div>
            <div class="form-text">Enter the prices from the provider's pricing page. Left blank, cost is not calculated for this provider.</div>
          </div>
        @endforeach

        <div class="card p-3 p-lg-4 mb-3">
          <h6 class="fw-bold mb-1">Usage Limits</h6>
          <p class="text-secondary small">Protects the budget and stops abuse. Each visitor message counts once.</p>
          <div class="row g-2">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Messages per visitor per hour</label>
              <input type="number" name="per_customer_hour" min="0" max="1000" class="form-control" value="{{ old('per_customer_hour', $perCustomerPerHour) }}" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold">Messages per day (whole site)</label>
              <input type="number" name="per_day_total" min="0" max="100000" class="form-control" value="{{ old('per_day_total', $perDayTotal) }}" required>
            </div>
          </div>
        </div>

        <button class="btn btn-primary">Save Assistant Settings</button>
      </form>

      @foreach ($providers as $key => $p)
        <form id="refresh-{{ $key }}" method="POST" action="{{ route('manage.ai-assistant.models.refresh', $key) }}">
          @csrf
        </form>
      @endforeach

      <form method="POST" action="{{ route('manage.ai-assistant.test') }}" class="mt-2">
        @csrf
        <button class="btn btn-outline-secondary">Test Primary Provider</button>
        <span class="text-secondary small ms-2">Sends one tiny message to check the key and model. Uses a few tokens.</span>
      </form>
    </div>

    <div class="col-lg-5">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">How it stays safe</h6>
        <ul class="small text-secondary mb-0 ps-3" style="line-height:1.7">
          <li>The assistant only recommends packages that are <strong>active</strong> in the catalogue, with prices taken from the database.</li>
          <li>Booking, payment, refund and complaint questions are handed to staff, not answered by the AI.</li>
          <li>No personal details (names, contacts, IDs, bookings) are sent to any AI provider.</li>
          <li>Keys are encrypted and never sent back to the browser. Changes are written to the log without the key.</li>
        </ul>
      </div>
    </div>
  </div>
@endsection
