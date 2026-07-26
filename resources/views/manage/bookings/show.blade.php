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
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#revisionModal">📝 Request Revision</button>
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
      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-details" type="button">Details</button></li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity" type="button">
            Activity Log
            @if ($booking->versions->isNotEmpty())<span class="badge text-bg-light border ms-1">v{{ $booking->versions->max('version') }}</span>@endif
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button">
            Documents
            @if ($booking->documents->isNotEmpty())<span class="badge text-bg-light border ms-1">{{ $booking->documents->count() }}</span>@endif
          </button>
        </li>
      </ul>

      <div class="tab-content">
      <div class="tab-pane fade show active" id="tab-details">
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
            <div class="text-secondary small">Rooms</div>
            <div class="fw-semibold">{{ $booking->rooms->sum('rooms') ?: '—' }} ({{ $booking->rooms->count() }} type{{ $booking->rooms->count() === 1 ? '' : 's' }})</div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Pax</div>
            <div class="fw-semibold">{{ $booking->adults }}A · {{ $booking->children }}C{{ $booking->seniors ? ' · ' . $booking->seniors . 'S' : '' }} · {{ $booking->infants }}I ({{ $booking->total_pax }})</div>
          </div>
          @if ($booking->pickup_location || $booking->arrival_time)
            <div class="col-md-8">
              <div class="text-secondary small">Pickup Location</div>
              <div class="fw-semibold">{{ $booking->pickup_location ?: '—' }}</div>
            </div>
            <div class="col-md-4">
              <div class="text-secondary small">Arrival Time</div>
              <div class="fw-semibold">{{ $booking->arrival_time ? substr($booking->arrival_time, 0, 5) : '—' }}</div>
            </div>
          @endif
        </div>

        @if ($booking->rooms->isNotEmpty())
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Room Type</th><th class="text-center">Rooms</th><th class="text-center">Pax</th><th class="text-end">Rate/pax</th><th class="text-end">Subtotal</th></tr></thead>
              <tbody>
                @foreach ($booking->rooms as $room)
                  <tr>
                    <td class="fw-semibold">{{ $room->room_name }} <span class="text-secondary fw-normal small">({{ $room->capacity }} pax/room)</span></td>
                    <td class="text-center">{{ $room->rooms }}</td>
                    <td class="text-center small">{{ $room->adults }}A · {{ $room->children }}C{{ $room->seniors ? ' · ' . $room->seniors . 'S' : '' }} · {{ $room->infants }}I</td>
                    <td class="text-end small">RM {{ number_format($room->adult_price, 2) }}</td>
                    <td class="text-end fw-semibold">RM {{ number_format($room->subtotal, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        @if ($booking->notes)
          <div class="mt-3 p-2 bg-light rounded small"><strong>Notes:</strong> {{ $booking->notes }}</div>
        @endif
        @if ($booking->provider_note)
          <div class="mt-2 p-2 bg-primary bg-opacity-10 rounded small"><strong>Provider note:</strong> {{ $booking->provider_note }}</div>
        @endif
        @if ($booking->rejection_reason)
          <div class="mt-2 p-2 bg-danger bg-opacity-10 rounded small"><strong>Rejection reason:</strong> {{ $booking->rejection_reason }}</div>
        @endif
        @if ($booking->openRevisionRequest)
          <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded small">
            <strong>Awaiting agent revision:</strong> {{ $booking->openRevisionRequest->remark }}
            <div class="text-secondary mt-1">
              Fields: {{ implode(', ', $booking->openRevisionRequest->fieldLabels()) }}
              · asked by {{ $booking->openRevisionRequest->requester?->name ?? 'system' }}
              {{ $booking->openRevisionRequest->created_at->diffForHumans() }}
            </div>
          </div>
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
              <thead class="table-light"><tr><th>Name</th><th>Type</th><th>Age</th><th>IC / Passport</th><th>Nationality</th></tr></thead>
              <tbody>
                @foreach ($booking->pax as $p)
                  <tr><td>{{ $p->name }} @if($p->is_lead)<span class="badge text-bg-primary ms-1">Lead</span>@endif</td><td class="text-capitalize">{{ $p->type }}</td><td>{{ $p->age !== null ? $p->age . ' yrs' : '—' }}</td><td>{{ $p->ic_passport_no ?? '—' }}</td><td>{{ $p->nationality ?? '—' }}</td></tr>
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

      </div><!-- /tab-details -->

      <div class="tab-pane fade" id="tab-activity">
        <!-- Amendments (Revision History lives in the sidebar, always visible) -->
        @if ($booking->amendments->isNotEmpty())
          <div class="card p-3 p-lg-4 mb-3">
            <h6 class="fw-bold mb-3">Amendment Requests</h6>
            @foreach ($booking->amendments as $am)
              <div class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold small">
                      {{ $am->typeLabel() }}
                      <span class="badge text-bg-{{ $am->statusBadge() }} ms-1">{{ ucfirst($am->status) }}</span>
                    </div>
                    <div class="small text-secondary mt-1">{{ $am->reason }}</div>
                    <div class="text-secondary mt-1" style="font-size:.72rem">
                      {{ $am->requester?->name ?? 'Agent' }} · {{ $am->created_at->format('d M Y, H:i') }}
                      @if ($am->reviewed_at)
                        · reviewed by {{ $am->reviewer?->name ?? 'staff' }} {{ $am->reviewed_at->format('d M Y, H:i') }}
                      @endif
                    </div>
                    @if ($am->admin_note)<div class="small fst-italic mt-1">“{{ $am->admin_note }}”</div>@endif
                  </div>
                  <div class="text-end small" style="min-width:9rem">
                    <div class="text-secondary" style="font-size:.72rem">From</div>
                    <div>{{ $am->current_value ?? '—' }}</div>
                    <div class="text-secondary mt-1" style="font-size:.72rem">To</div>
                    <div class="fw-semibold">
                      {{ optional($am->packageDate?->depart_date)->format('d M Y')
                         ?? optional($am->requested_date)->format('d M Y')
                         ?? $am->requested_pickup_location ?? '—' }}
                    </div>
                  </div>
                </div>

                @if ($am->status === 'pending')
                  <form method="POST" action="{{ route('manage.bookings.amendments.approve', [$booking, $am]) }}" class="mt-3">
                    @csrf
                    <div class="input-group input-group-sm">
                      <input type="text" name="admin_note" class="form-control" placeholder="Note (optional)" maxlength="500">
                      <button class="btn btn-success">✔ Approve &amp; Apply</button>
                      <button class="btn btn-outline-danger"
                              formaction="{{ route('manage.bookings.amendments.reject', [$booking, $am]) }}">✕ Reject</button>
                    </div>
                    <div class="form-text small">Approving moves seats between departures and reissues the invoice &amp; voucher.</div>
                  </form>
                @endif
              </div>
            @endforeach
          </div>
        @endif

        <!-- Timeline -->
        <div class="card p-3 p-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Activity History</h6>
            <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#noteModal">＋ Note</button>
          </div>
          <ul class="list-unstyled mb-0">
            @foreach ($booking->timeline as $t)
              <li class="d-flex gap-2 pb-3 position-relative">
                <div class="rounded-circle bg-primary" style="width:10px;height:10px;margin-top:5px;flex:0 0 auto"></div>
                <div>
                  <div class="small fw-semibold">
                    {{ $t->action }}
                    @if ($t->version)
                      <a href="{{ route('manage.bookings.versions.show', [$booking, $t->version]) }}" class="small ms-1">view changes</a>
                    @endif
                  </div>
                  @if ($t->note)<div class="small text-secondary">{{ $t->note }}</div>@endif
                  <div class="text-secondary" style="font-size:.72rem">{{ $t->user?->name ?? 'System' }} · {{ $t->created_at->format('d M Y, H:i') }}</div>
                </div>
              </li>
            @endforeach
          </ul>
        </div>
      </div><!-- /tab-activity -->

      <div class="tab-pane fade" id="tab-documents">
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
      </div><!-- /tab-documents -->
      </div><!-- /tab-content -->
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

      {{-- Revision History sits beside Details, not inside a tab — the client's desktop
           mockup keeps it visible while staff read the booking. --}}
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Revision History</h6>
        @if ($booking->versions->isEmpty())
          <div class="text-secondary small">No revisions — this booking has not been sent back to the agent.</div>
        @else
          <div class="list-group list-group-flush">
            @foreach ($booking->versions as $v)
              <a href="{{ route('manage.bookings.versions.show', [$booking, $v]) }}"
                 class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold small">
                    Version {{ $v->version }}
                    @if ($loop->first)<span class="badge text-bg-primary ms-1">Latest</span>@endif
                  </div>
                  <div class="text-secondary" style="font-size:.72rem">
                    {{ $v->reasonLabel() }} · {{ $v->author?->name ?? 'System' }} · {{ $v->created_at->format('d M Y, H:i') }}
                  </div>
                </div>
                <span class="text-secondary">›</span>
              </a>
            @endforeach
          </div>
        @endif
      </div>

    </div>
  </div>

  <!-- Request Revision Modal -->
  <div class="modal fade" id="revisionModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('manage.bookings.revision', $booking) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">📝 Request Revision</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">What must the agent correct? <span class="text-danger">*</span></label>
        <textarea name="remark" rows="3" class="form-control" required maxlength="1000" placeholder="e.g. Child age is missing and the payment receipt is unreadable."></textarea>
        <div class="form-text small">The agent sees this word for word on their booking.</div>

        <label class="form-label small fw-semibold mt-3">Fields to fix <span class="text-danger">*</span></label>
        <div class="form-text small mb-2">Tick at least one. These are highlighted on the agent's edit form.</div>
        <div class="row g-3">
          @foreach (\App\Models\BookingRevisionRequest::fieldsByGroup() as $group => $fields)
            <div class="col-md-4">
              <div class="border rounded p-2 h-100">
                <div class="text-secondary small fw-semibold mb-1">{{ $group }}</div>
                @foreach ($fields as $key => $label)
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fields[]" value="{{ $key }}" id="rf-{{ $loop->parent->index }}-{{ $loop->index }}">
                    <label class="form-check-label small" for="rf-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $label }}</label>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-warning">Send Back to Agent</button></div>
    </form>
  </div></div></div>

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
