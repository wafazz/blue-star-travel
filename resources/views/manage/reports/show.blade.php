@extends('layouts.admin')
@section('title', $report['title'])
@section('console', 'Management')
@section('heading', $report['title'])

@php
  $f = $report['filters'];
  $cell = function ($v, $format) {
      if ($format === 'money') {
          return 'RM ' . number_format((float) $v, 2);
      }
      if ($format === 'int') {
          return number_format((int) $v);
      }
      if ($format === 'percent') {
          return number_format((float) $v, 2) . '%';
      }
      return $v;
  };
  $chartMax = 1;
  foreach (($report['chart']['series'] ?? []) as $s) {
      $chartMax = max($chartMax, abs($s['value']));
  }
@endphp

@section('content')
  <div class="card p-3 p-lg-4 mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small text-secondary mb-1">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="{{ $report['from']->format('Y-m-d') }}">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small text-secondary mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="{{ $report['to']->format('Y-m-d') }}">
      </div>

      @if ($report['key'] === 'sales')
        <div class="col-6 col-md-2">
          <label class="form-label small text-secondary mb-1">Group by</label>
          <select name="group" class="form-select form-select-sm">
            <option value="day" @selected(($f['group'] ?? 'day') === 'day')>Day</option>
            <option value="month" @selected(($f['group'] ?? '') === 'month')>Month</option>
          </select>
        </div>
      @endif

      @if ($report['key'] === 'bookings')
        <div class="col-6 col-md-2">
          <label class="form-label small text-secondary mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach (\App\Models\Booking::STATUSES as $k => $label)
              <option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-secondary mb-1">Type</label>
          <select name="type" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach (\App\Models\Booking::TYPES as $k => $label)
              <option value="{{ $k }}" @selected(($f['type'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      @endif

      @if ($report['key'] === 'packages')
        <div class="col-6 col-md-3">
          <label class="form-label small text-secondary mb-1">Category</label>
          <select name="category" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach (\App\Models\Package::CATEGORIES as $k => $label)
              <option value="{{ $k }}" @selected(($f['category'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      @endif

      @if ($report['key'] === 'customers')
        <div class="col-6 col-md-3">
          <label class="form-label small text-secondary mb-1">Show</label>
          <select name="active_only" class="form-select form-select-sm">
            <option value="">All customers</option>
            <option value="1" @selected(($f['active_only'] ?? '') === '1')>With bookings only</option>
          </select>
        </div>
      @endif

      @if ($report['key'] === 'commission')
        <div class="col-6 col-md-2">
          <label class="form-label small text-secondary mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'reversed' => 'Reversed'] as $k => $label)
              <option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small text-secondary mb-1">Level</label>
          <input type="number" min="1" name="level" class="form-control form-control-sm" value="{{ $f['level'] ?? '' }}" placeholder="All">
        </div>
      @endif

      <div class="col-md-2">
        <button class="btn btn-primary btn-sm w-100">Apply</button>
      </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
      <div class="text-secondary small">
        {{ $report['subtitle'] }} ·
        <span class="fw-semibold">{{ $report['from']->format('d M Y') }} — {{ $report['to']->format('d M Y') }}</span>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('manage.reports.index') }}" class="btn btn-sm btn-outline-secondary">All Reports</a>
        <a href="{{ route('manage.reports.export', ['key' => $report['key'], 'format' => 'pdf']) }}?{{ http_build_query(request()->query()) }}" class="btn btn-sm btn-outline-danger">📄 PDF</a>
        <a href="{{ route('manage.reports.export', ['key' => $report['key'], 'format' => 'excel']) }}?{{ http_build_query(request()->query()) }}" class="btn btn-sm btn-outline-success">📊 Excel</a>
        <a href="{{ route('manage.reports.export', ['key' => $report['key'], 'format' => 'csv']) }}?{{ http_build_query(request()->query()) }}" class="btn btn-sm btn-outline-primary">📁 CSV</a>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    @foreach ($report['kpis'] as $kpi)
      <div class="col-6 col-lg-3">
        <div class="card h-100 p-3">
          <span class="rounded-3 d-flex align-items-center justify-content-center fs-5 text-bg-{{ $kpi['tone'] }} bg-opacity-10" style="width:40px;height:40px">{{ $kpi['icon'] }}</span>
          <div class="fs-5 fw-bold mt-3 text-truncate" title="{{ $kpi['value'] }}">{{ $kpi['value'] }}</div>
          <div class="text-secondary small">{{ $kpi['label'] }}</div>
        </div>
      </div>
    @endforeach
  </div>

  @if (! empty($report['chart']['series']))
    <div class="card p-3 p-lg-4 mb-3">
      <h6 class="fw-bold mb-4">{{ $report['chart']['title'] }}</h6>
      <div class="d-flex align-items-end justify-content-between gap-2" style="height:220px">
        @foreach ($report['chart']['series'] as $s)
          <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height:100%">
            <div class="small fw-semibold mb-1" style="font-size:.7rem">{{ $s['value'] >= 1000 ? number_format($s['value'] / 1000, 1) . 'k' : number_format($s['value']) }}</div>
            <div class="w-100 rounded-top" style="background:linear-gradient(180deg,#1466ff,#0b3fd1);height:{{ max(2, round(abs($s['value']) / $chartMax * 100)) }}%;min-height:2px;transition:.3s"></div>
            <div class="text-secondary text-center mt-2" style="font-size:.7rem">{{ \Illuminate\Support\Str::limit($s['label'], 12) }}</div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="card p-3 p-lg-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">{{ $report['title'] }} — {{ count($report['rows']) }} row(s)</h6>
    </div>
    <div class="table-responsive" style="max-height:640px;overflow-y:auto">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light position-sticky top-0">
          <tr>
            @foreach ($report['columns'] as $col)
              <th class="{{ in_array($col['format'], ['money', 'int', 'percent']) ? 'text-end' : '' }}">{{ $col['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($report['rows'] as $row)
            <tr>
              @foreach ($report['columns'] as $i => $col)
                <td class="small {{ in_array($col['format'], ['money', 'int', 'percent']) ? 'text-end' : '' }} {{ $col['format'] === 'money' && (float) $row[$i] < 0 ? 'text-danger' : '' }}">
                  {{ $cell($row[$i] ?? '', $col['format']) }}
                </td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-secondary py-4">No data for this period.</td></tr>
          @endforelse
        </tbody>
        @if (! empty($report['totals']))
          <tfoot class="table-light">
            <tr class="fw-bold">
              @foreach ($report['columns'] as $i => $col)
                <td class="small {{ in_array($col['format'], ['money', 'int', 'percent']) ? 'text-end' : '' }}">
                  {{ isset($report['totals'][$i]) ? (is_string($report['totals'][$i]) ? $report['totals'][$i] : $cell($report['totals'][$i], $col['format'])) : '' }}
                </td>
              @endforeach
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>
@endsection
