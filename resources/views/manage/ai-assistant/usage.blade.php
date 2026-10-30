@extends('layouts.admin')
@section('title', 'AI Usage')
@section('console', 'Management')
@section('heading', 'AI Usage')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Period">
      @foreach ($periods as $period)
        <a class="btn btn-outline-primary {{ $days === $period ? 'active' : '' }}" href="{{ route('manage.ai-assistant.usage', ['days' => $period]) }}">Last {{ $period }} days</a>
      @endforeach
    </div>
    <span class="small text-secondary">Replies and staff drafts</span>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card p-3"><div class="small text-secondary">Calls</div><div class="h4 mb-0">{{ number_format($totals['calls']) }}</div></div></div>
    <div class="col-md-4"><div class="card p-3"><div class="small text-secondary">Tokens (in / out)</div><div class="h4 mb-0">{{ number_format($totals['input_tokens']) }} / {{ number_format($totals['output_tokens']) }}</div></div></div>
    <div class="col-md-4">
      <div class="card p-3">
        <div class="small text-secondary">Estimated cost</div>
        <div class="h4 mb-0">{{ $totals['cost'] !== null ? number_format($totals['cost'], 2) : 'Enter prices in settings' }}</div>
      </div>
    </div>
  </div>

  <div class="card p-3 p-lg-4 mb-3">
    <h6 class="fw-bold mb-3">By provider</h6>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Provider</th><th class="text-end">Calls</th><th class="text-end">Input tokens</th><th class="text-end">Output tokens</th><th class="text-end">Cost</th></tr></thead>
        <tbody>
          @foreach ($providers as $p)
            <tr>
              <td>{{ $p['label'] }}</td>
              <td class="text-end">{{ number_format($p['calls']) }}</td>
              <td class="text-end">{{ number_format($p['input_tokens']) }}</td>
              <td class="text-end">{{ number_format($p['output_tokens']) }}</td>
              <td class="text-end">
                @if ($p['cost'] !== null) {{ number_format($p['cost'], 2) }}
                @elseif (! $p['has_prices']) <span class="text-secondary small">no price set</span>
                @else 0.00 @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card p-3 p-lg-4">
    <h6 class="fw-bold mb-3">Per day</h6>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Day</th><th class="text-end">Calls</th><th class="text-end">Tokens</th></tr></thead>
        <tbody>
          @forelse ($daily as $d)
            <tr><td>{{ $d['day'] }}</td><td class="text-end">{{ number_format($d['calls']) }}</td><td class="text-end">{{ number_format($d['tokens']) }}</td></tr>
          @empty
            <tr><td colspan="3" class="text-secondary small">No usage in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
