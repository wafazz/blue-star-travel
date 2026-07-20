@extends('layouts.admin')
@section('title', 'Coupons')
@section('console', 'Management')
@section('heading', 'Discount Coupons')

@section('content')
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">New Coupon</h6>
        <form method="POST" action="{{ route('manage.coupons.store') }}">
          @csrf
          <label class="form-label small fw-semibold">Code</label>
          <input type="text" name="code" class="form-control mb-2 text-uppercase" placeholder="RAYA2026" required>
          <label class="form-label small fw-semibold">Description</label>
          <input type="text" name="description" class="form-control mb-2" placeholder="Raya promo">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Type</label><select name="discount_type" class="form-select"><option value="percent">Percent %</option><option value="fixed">Fixed RM</option></select></div>
            <div class="col-6"><label class="form-label small fw-semibold">Value</label><input type="number" name="discount_value" step="0.01" min="0" class="form-control" required></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Min Spend</label><input type="number" name="min_spend" step="0.01" min="0" value="0" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Max Discount</label><input type="number" name="max_discount" step="0.01" min="0" class="form-control" placeholder="—"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Usage Limit</label><input type="number" name="usage_limit" min="1" class="form-control" placeholder="∞"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Expires</label><input type="date" name="expires_at" class="form-control"></div>
          </div>
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="active" value="1" checked><label class="form-check-label small">Active</label></div>
          <button class="btn btn-brand w-100">＋ Create Coupon</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Discount</th><th>Min</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse ($coupons as $c)
                <tr>
                  <td class="fw-semibold">{{ $c->code }}<div class="text-secondary" style="font-size:.72rem">{{ $c->description }}</div></td>
                  <td>{{ $c->discount_type === 'percent' ? rtrim(rtrim(number_format($c->discount_value, 2), '0'), '.') . '%' : 'RM ' . number_format($c->discount_value, 2) }}</td>
                  <td class="small">RM {{ number_format($c->min_spend, 0) }}</td>
                  <td class="small">{{ $c->used_count }}{{ $c->usage_limit ? '/' . $c->usage_limit : '' }}</td>
                  <td class="small">{{ optional($c->expires_at)->format('d M Y') ?? '—' }}</td>
                  <td><span class="badge text-bg-{{ $c->active && ! $c->isExhausted() ? 'success' : 'secondary' }}">{{ $c->active ? ($c->isExhausted() ? 'Exhausted' : 'Active') : 'Inactive' }}</span></td>
                  <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit{{ $c->id }}">Edit</button>
                    <form method="POST" action="{{ route('manage.coupons.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Delete coupon?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑</button></form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">No coupons yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-3">{{ $coupons->links() }}</div>
    </div>
  </div>

  @foreach ($coupons as $c)
    <div class="modal fade" id="edit{{ $c->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
      <form method="POST" action="{{ route('manage.coupons.update', $c) }}">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit {{ $c->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-6"><label class="form-label small fw-semibold">Code</label><input type="text" name="code" value="{{ $c->code }}" class="form-control text-uppercase" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Description</label><input type="text" name="description" value="{{ $c->description }}" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Type</label><select name="discount_type" class="form-select"><option value="percent" @selected($c->discount_type === 'percent')>Percent %</option><option value="fixed" @selected($c->discount_type === 'fixed')>Fixed RM</option></select></div>
            <div class="col-6"><label class="form-label small fw-semibold">Value</label><input type="number" name="discount_value" step="0.01" value="{{ $c->discount_value }}" class="form-control" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">Min Spend</label><input type="number" name="min_spend" step="0.01" value="{{ $c->min_spend }}" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Max Discount</label><input type="number" name="max_discount" step="0.01" value="{{ $c->max_discount }}" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Usage Limit</label><input type="number" name="usage_limit" value="{{ $c->usage_limit }}" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Expires</label><input type="date" name="expires_at" value="{{ optional($c->expires_at)->format('Y-m-d') }}" class="form-control"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($c->active)><label class="form-check-label small">Active</label></div></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-brand">Save</button></div>
      </form>
    </div></div></div>
  @endforeach
@endsection
