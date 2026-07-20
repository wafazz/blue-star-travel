@extends('layouts.admin')
@section('title', 'Booking ' . $booking->booking_no)
@section('console', 'Management')
@section('heading', 'Booking ' . $booking->booking_no)

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('manage.bookings.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
      <span class="badge text-bg-{{ $booking->statusBadge() }} fs-6">{{ $booking->statusLabel() }}</span>
      <span class="badge text-bg-light border">{{ \App\Models\Booking::TYPES[$booking->type] ?? $booking->type }}</span>
      @if ($booking->status === 'waiting_provider_confirmation')
        <span class="badge text-bg-{{ $booking->provider_status === 'approved' ? 'success' : ($booking->provider_status === 'rejected' ? 'danger' : 'warning') }}">Provider: {{ ucfirst($booking->provider_status) }}</span>
      @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
      @if ($booking->status === 'pending_verification')
        <form method="POST" action="{{ route('manage.bookings.submit', $booking) }}">@csrf<button class="btn btn-sm btn-primary">✓ Verify & Send to Provider</button></form>
      @endif
      @if (in_array($booking->status, ['pending_verification', 'waiting_provider_confirmation']))
        <form method="POST" action="{{ route('manage.bookings.confirm', $booking) }}">@csrf<button class="btn btn-sm btn-success">✔ Confirm Booking</button></form>
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">✕ Reject</button>
      @endif
      @if ($booking->status === 'confirmed')
        <form method="POST" action="{{ route('manage.bookings.complete', $booking) }}">@csrf<button class="btn btn-sm btn-outline-success">🏁 Mark Completed</button></form>
      @endif
      @if (! in_array($booking->status, ['cancelled', 'completed', 'rejected']))
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
      @endif
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Overview -->
      <div class="card p-3 p-lg-4 mb-3">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-secondary small">Package</div>
            <div class="fw-semibold">{{ $booking->package?->title ?? '—' }}</div>
            <div class="small text-secondary">{{ $booking->package?->code }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Provider</div>
            <div class="fw-semibold">{{ $booking->provider?->name ?? '—' }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Customer</div>
            <div class="fw-semibold">{{ $booking->customer?->name ?? '—' }}</div>
            <div class="small text-secondary">{{ $booking->customer?->phone }} · {{ $booking->customer?->email }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Agent</div>
            <div class="fw-semibold">{{ $booking->agent?->name ?? 'Direct / House' }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Departure</div>
            <div class="fw-semibold">{{ optional($booking->packageDate?->depart_date)->format('d M Y') ?? optional($booking->travel_date)->format('d M Y') ?? '—' }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Pricing Tier</div>
            <div class="fw-semibold">{{ $booking->pricing?->tier_name ?? '—' }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Pax</div>
            <div class="fw-semibold">{{ $booking->adults }}A · {{ $booking->children }}C · {{ $booking->infants }}I ({{ $booking->total_pax }})</div>
          </div>
        </div>
        @if ($booking->notes)
          <div class="mt-3 p-2 bg-light rounded small"><strong>Notes:</strong> {{ $booking->notes }}</div>
        @endif
        @if ($booking->provider_note)
          <div class="mt-2 p-2 bg-primary bg-opacity-10 rounded small"><strong>Provider note:</strong> {{ $booking->provider_note }}</div>
        @endif
        @if ($booking->rejection_reason)
          <div class="mt-2 p-2 bg-danger bg-opacity-10 rounded small"><strong>Rejection reason:</strong> {{ $booking->rejection_reason }}</div>
        @endif
      </div>

      <!-- Passengers -->
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Passengers</h6>
        @if ($booking->pax->isEmpty())
          <div class="text-secondary small">No passenger details captured.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Name</th><th>Type</th><th>IC / Passport</th><th>Nationality</th></tr></thead>
              <tbody>
                @foreach ($booking->pax as $p)
                  <tr><td>{{ $p->name }} @if($p->is_lead)<span class="badge text-bg-primary ms-1">Lead</span>@endif</td><td class="text-capitalize">{{ $p->type }}</td><td>{{ $p->ic_passport_no ?? '—' }}</td><td>{{ $p->nationality ?? '—' }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <!-- Payments -->
      <div class="card p-3 p-lg-4 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Payments</h6>
          @if (! in_array($booking->status, ['cancelled', 'rejected']) && $booking->balance() > 0)
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#fpxModal">⚡ Pay via FPX</button>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">＋ Record Payment</button>
            </div>
          @endif
        </div>
        @if ($booking->payments->isEmpty())
          <div class="text-secondary small">No payments recorded.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Date</th><th>Method</th><th class="text-capitalize">Type</th><th class="text-end">Amount</th><th>Status</th><th>Slip</th><th></th></tr></thead>
              <tbody>
                @foreach ($booking->payments as $pay)
                  <tr>
                    <td class="small">{{ optional($pay->paid_at)->format('d M Y') }}</td>
                    <td class="small">{{ $pay->methodLabel() }}@if($pay->reference)<div class="text-secondary" style="font-size:.75rem">{{ $pay->reference }}</div>@endif</td>
                    <td class="small text-capitalize">{{ $pay->type }}</td>
                    <td class="text-end fw-semibold">RM {{ number_format($pay->amount, 2) }}</td>
                    <td><span class="badge text-bg-{{ $pay->status === 'verified' ? 'success' : ($pay->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($pay->status) }}</span></td>
                    <td>@if($pay->slip_path)<a href="{{ route('payments.slip', $pay) }}" target="_blank" class="small">View</a>@else<span class="text-secondary">—</span>@endif</td>
                    <td class="text-end">
                      @if ($pay->status === 'pending')
                        <form method="POST" action="{{ route('manage.payments.verify', $pay) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success py-0">✓</button></form>
                        <form method="POST" action="{{ route('manage.payments.reject', $pay) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger py-0">✕</button></form>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <!-- Documents -->
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Documents</h6>
        @if ($booking->documents->isEmpty())
          <div class="text-secondary small">No documents yet. Confirm the booking to generate invoice & travel voucher.</div>
        @else
          <div class="d-flex flex-wrap gap-2">
            @foreach ($booking->documents as $doc)
              <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary">📄 {{ $doc->typeLabel() }}</a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <!-- Sidebar: money + timeline -->
    <div class="col-lg-4">
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Financials</h6>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Subtotal</span><span>RM {{ number_format($booking->subtotal, 2) }}</span></div>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Discount</span><span>− RM {{ number_format($booking->discount, 2) }}</span></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between mb-2"><span class="fw-semibold">Total</span><span class="fw-bold">RM {{ number_format($booking->total_amount, 2) }}</span></div>
        <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Paid</span><span class="text-success">RM {{ number_format($booking->paid_amount, 2) }}</span></div>
        <div class="d-flex justify-content-between"><span class="fw-semibold">Balance</span><span class="fw-bold {{ $booking->balance() > 0 ? 'text-danger' : 'text-success' }}">RM {{ number_format($booking->balance(), 2) }}</span></div>
        @if ($booking->refundedAmount() > 0)
          <div class="d-flex justify-content-between small mt-2"><span class="text-secondary">Refunded</span><span class="text-danger">− RM {{ number_format($booking->refundedAmount(), 2) }}</span></div>
        @endif
        @if ($booking->paid_amount > 0 && $booking->refundedAmount() < $booking->paid_amount && ! in_array($booking->status, ['refunded']))
          <button class="btn btn-sm btn-outline-danger w-100 mt-3" data-bs-toggle="modal" data-bs-target="#refundModal">↩️ Request Refund</button>
        @endif
        @if ($booking->refunds->isNotEmpty())
          <hr class="my-2">
          <div class="text-secondary small mb-1">Refunds</div>
          @foreach ($booking->refunds as $rf)
            <div class="d-flex justify-content-between small"><span>{{ $rf->refund_no }}</span><span><span class="badge text-bg-{{ $rf->statusBadge() }}">{{ ucfirst($rf->status) }}</span> RM {{ number_format($rf->amount, 2) }}</span></div>
          @endforeach
        @endif
      </div>

      <div class="card p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Timeline</h6>
          <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#noteModal">＋ Note</button>
        </div>
        <ul class="list-unstyled mb-0">
          @foreach ($booking->timeline as $t)
            <li class="d-flex gap-2 pb-3 position-relative">
              <div class="rounded-circle bg-primary" style="width:10px;height:10px;margin-top:5px;flex:0 0 auto"></div>
              <div>
                <div class="small fw-semibold">{{ $t->action }}</div>
                @if ($t->note)<div class="small text-secondary">{{ $t->note }}</div>@endif
                <div class="text-secondary" style="font-size:.72rem">{{ $t->user?->name ?? 'System' }} · {{ $t->created_at->format('d M Y, H:i') }}</div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  <!-- Reject Modal -->
  <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.reject', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Reject Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><label class="form-label small fw-semibold">Reason</label><textarea name="rejection_reason" rows="3" class="form-control" placeholder="Why is this booking rejected?"></textarea></div>
      <div class="modal-footer"><button class="btn btn-danger">Reject Booking</button></div>
    </form>
  </div></div></div>

  <!-- Cancel Modal -->
  <div class="modal fade" id="cancelModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.cancel', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Cancel Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><label class="form-label small fw-semibold">Reason (optional)</label><textarea name="reason" rows="3" class="form-control"></textarea></div>
      <div class="modal-footer"><button class="btn btn-outline-danger">Cancel Booking</button></div>
    </form>
  </div></div></div>

  <!-- Note Modal -->
  <div class="modal fade" id="noteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.note', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Add Note</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><textarea name="note" rows="3" class="form-control" required placeholder="Internal note…"></textarea></div>
      <div class="modal-footer"><button class="btn btn-primary">Add Note</button></div>
    </form>
  </div></div></div>

  <!-- Payment Modal -->
  <div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.payment', $booking) }}" enctype="multipart/form-data">@csrf
      <div class="modal-header"><h5 class="modal-title">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6"><label class="form-label small fw-semibold">Amount (RM)</label><input type="number" name="amount" step="0.01" min="0.01" value="{{ number_format($booking->balance(), 2, '.', '') }}" class="form-control" required></div>
          <div class="col-6"><label class="form-label small fw-semibold">Type</label><select name="type" class="form-select">
            <option value="deposit">Deposit</option><option value="partial">Partial</option><option value="balance">Balance</option><option value="full" selected>Full</option></select></div>
          <div class="col-6"><label class="form-label small fw-semibold">Method</label><select name="method" class="form-select">
            @foreach (\App\Models\Payment::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
          <div class="col-6"><label class="form-label small fw-semibold">Reference</label><input type="text" name="reference" class="form-control"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Payment Slip (optional)</label><input type="file" name="slip" accept="image/*" class="form-control"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Note</label><input type="text" name="note" class="form-control"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-brand">Record Payment</button></div>
    </form>
  </div></div></div>

  <!-- FPX Modal -->
  <div class="modal fade" id="fpxModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('gateway.initiate', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">⚡ Pay via FPX (Sandbox)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Amount (RM)</label>
        <input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format($booking->balance(), 2, '.', '') }}" value="{{ number_format($booking->balance(), 2, '.', '') }}" class="form-control" required>
        <div class="form-text">You'll be redirected to the FPX bank-selection screen.</div>
      </div>
      <div class="modal-footer"><button class="btn btn-success">Continue to FPX</button></div>
    </form>
  </div></div></div>

  <!-- Refund Modal -->
  <div class="modal fade" id="refundModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.refund', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Request Refund</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6"><label class="form-label small fw-semibold">Amount (RM)</label><input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format($booking->paid_amount, 2, '.', '') }}" value="{{ number_format(max(0, $booking->paid_amount - $booking->refundedAmount()), 2, '.', '') }}" class="form-control" required></div>
          <div class="col-6"><label class="form-label small fw-semibold">Method</label><select name="method" class="form-select">@foreach (\App\Models\Refund::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
          <div class="col-12"><label class="form-label small fw-semibold">Reason</label><textarea name="reason" rows="2" class="form-control" placeholder="Cancellation, overpayment…"></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-danger">Submit Refund Request</button></div>
    </form>
  </div></div></div>
@endsection
