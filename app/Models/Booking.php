<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'travel_date'           => 'date',
        'return_date'           => 'date',
        'submitted_at'          => 'datetime',
        'sent_to_provider_at'   => 'datetime',
        'provider_responded_at' => 'datetime',
        'confirmed_at'          => 'datetime',
        'rejected_at'           => 'datetime',
        'cancelled_at'          => 'datetime',
        'completed_at'          => 'datetime',
        'revision_requested_at' => 'datetime',
        'resubmitted_at'        => 'datetime',
    ];

    const STATUSES = [
        'draft'                         => 'Draft',
        'pending_payment'               => 'Pending Payment',
        'pending_verification'          => 'Pending Verification',
        'needs_revision'                => 'Needs Revision',
        'waiting_provider_confirmation' => 'Waiting Provider',
        'confirmed'                     => 'Confirmed',
        'rejected'                      => 'Rejected',
        'cancelled'                     => 'Cancelled',
        'completed'                     => 'Completed',
        'refunded'                      => 'Refunded',
    ];

    const STATUS_BADGE = [
        'draft'                         => 'secondary',
        'pending_payment'               => 'warning',
        'pending_verification'          => 'info',
        'needs_revision'                => 'warning',
        'waiting_provider_confirmation' => 'primary',
        'confirmed'                     => 'success',
        'rejected'                      => 'danger',
        'cancelled'                     => 'secondary',
        'completed'                     => 'success',
        'refunded'                      => 'dark',
    ];

    // The agent portal collapses the 10 staff statuses into the 6 labels the client
    // asked for. Staff screens keep STATUSES; only this side simplifies.
    const AGENT_STATUS = [
        'draft'                         => ['Draft', 'secondary'],
        'pending_payment'               => ['Submitted', 'info'],
        'pending_verification'          => ['Submitted', 'info'],
        // "Approved" in the client's Status Guide = admin accepted the information and
        // passed it to the provider. It is not yet a confirmed booking.
        'waiting_provider_confirmation' => ['Approved', 'primary'],
        'needs_revision'                => ['Need Revision', 'warning'],
        'confirmed'                     => ['Confirmed', 'success'],
        'completed'                     => ['Completed', 'dark'],
        'rejected'                      => ['Cancelled', 'danger'],
        'cancelled'                     => ['Cancelled', 'danger'],
        'refunded'                      => ['Cancelled', 'danger'],
    ];

    // Tabs filter by LABEL, not status — a "Submitted" tab wired to a single status
    // would silently hide bookings in the other statuses that share the label.
    const AGENT_TABS = [
        'draft'          => 'Draft',
        'submitted'      => 'Submitted',
        'needs_revision' => 'Need Revision',
        'approved'       => 'Approved',
        'confirmed'      => 'Confirmed',
        'completed'      => 'Completed',
        'cancelled'      => 'Cancelled',
    ];

    /** The client's Status Guide legend: label → [badge, what it means]. */
    const AGENT_STATUS_GUIDE = [
        'Draft'         => ['secondary', 'Not submitted yet'],
        'Submitted'     => ['info', 'Waiting for review'],
        'Need Revision' => ['warning', 'Revision requested by admin'],
        'Approved'      => ['primary', 'Information approved'],
        'Confirmed'     => ['success', 'Booking confirmed'],
        'Completed'     => ['dark', 'Trip finished'],
        'Cancelled'     => ['danger', 'Booking cancelled'],
    ];

    const TYPES = [
        'manual'    => 'Manual',
        'online'    => 'Online',
        'group'     => 'Group',
        'family'    => 'Family',
        'corporate' => 'Corporate',
        'walk_in'   => 'Walk-in',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function packageDate(): BelongsTo
    {
        return $this->belongsTo(PackageDate::class);
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(PackagePricing::class, 'package_pricing_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function pax(): HasMany
    {
        return $this->hasMany(BookingPax::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function timeline(): HasMany
    {
        // id breaks the tie: several events can share a second, and `latest()` alone
        // would render them in an arbitrary order in the Activity Log.
        return $this->hasMany(BookingTimeline::class)->latest()->latest('id');
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(BookingRevisionRequest::class)->latest();
    }

    public function openRevisionRequest(): HasOne
    {
        return $this->hasOne(BookingRevisionRequest::class)->where('status', 'open')->latestOfMany();
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(BookingAmendment::class)->latest();
    }

    public function openAmendment(): HasOne
    {
        return $this->hasOne(BookingAmendment::class)->where('status', 'pending')->latestOfMany();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BookingVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(BookingVersion::class)->latestOfMany('version');
    }

    /** At most one staged edit per booking per editor. */
    public function drafts(): HasMany
    {
        return $this->hasMany(BookingDraft::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(BookingDraft::class)->latestOfMany();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest();
    }

    public function refundedAmount(): float
    {
        return (float) $this->refunds()->where('status', 'processed')->sum('amount');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }

    public function agentStatusLabel(): string
    {
        return self::AGENT_STATUS[$this->status][0] ?? $this->statusLabel();
    }

    public function agentStatusBadge(): string
    {
        return self::AGENT_STATUS[$this->status][1] ?? 'secondary';
    }

    // Every DB status that rolls up into one agent-facing tab.
    public static function statusesForTab(string $tab): array
    {
        $label = self::AGENT_TABS[$tab] ?? null;

        return $label ? array_keys(array_filter(self::AGENT_STATUS, fn ($s) => $s[0] === $label)) : [];
    }

    public function needsRevision(): bool
    {
        return $this->status === 'needs_revision';
    }

    // A finished or dead booking is the only thing an agent may not touch. Everything
    // else — including `confirmed` — can be edited and resubmitted for re-verification.
    const AGENT_LOCKED_STATUSES = ['completed', 'cancelled', 'rejected', 'refunded'];

    public function isEditableByAgent(): bool
    {
        return ! in_array($this->status, self::AGENT_LOCKED_STATUSES);
    }

    /**
     * Same open window as editing: anything not already finished or dead. The agent may
     * cancel, but never refund — HQ decides what goes back (Planning §13.9h).
     */
    public function isCancellableByAgent(): bool
    {
        return ! in_array($this->status, self::AGENT_LOCKED_STATUSES);
    }

    /** When the trip starts. A chosen departure wins; an open-dated booking uses travel_date. */
    public function arrivalDate(): ?Carbon
    {
        return $this->packageDate?->depart_date ?? $this->travel_date;
    }

    public function returnDate(): ?Carbon
    {
        if ($this->packageDate?->return_date) {
            return $this->packageDate->return_date;
        }

        // What the agent actually typed beats anything derived from the package length.
        if ($this->return_date) {
            return $this->return_date;
        }

        $nights = (int) ($this->package?->duration_nights ?? 0);

        return $nights > 0 ? $this->arrivalDate()?->copy()->addDays($nights) : null;
    }

    public function nights(): int
    {
        $from = $this->arrivalDate();
        $to = $this->returnDate();

        return $from && $to ? (int) $from->diffInDays($to) : (int) ($this->package?->duration_nights ?? 0);
    }

    /** "3 adults, 2 children" — zero counts are omitted. */
    public function paxSummary(): string
    {
        $parts = [];
        foreach (['adults' => 'adult', 'children' => 'child', 'seniors' => 'senior', 'infants' => 'infant'] as $field => $noun) {
            $n = (int) $this->{$field};
            if ($n > 0) {
                $parts[] = $n . ' ' . ($n === 1 ? $noun : ($noun === 'child' ? 'children' : $noun . 's'));
            }
        }

        return $parts ? implode(', ', $parts) : '—';
    }

    /** The trip is off, so its price is no longer owed — only the refund still matters. */
    const DEAD_STATUSES = ['cancelled', 'rejected', 'refunded'];

    public function isDead(): bool
    {
        return in_array($this->status, self::DEAD_STATUSES);
    }

    /**
     * A forfeited deposit is consumed OUT of what the customer paid, so it never inflates
     * the trip price — it reduces how far their money goes.
     */
    public function balance(): float
    {
        if ($this->isDead()) {
            return 0.0;
        }

        return round((float) $this->total_amount + (float) $this->forfeited_amount - (float) $this->paid_amount, 2);
    }

    /** What is left of the customer's money once the penalty has been taken out of it. */
    public function paidAfterForfeit(): float
    {
        return round((float) $this->paid_amount - (float) $this->forfeited_amount, 2);
    }

    /**
     * Every payment the agent has actually filed. A rejected slip is not money, so it is
     * left out of all four deposit figures — it stays visible in the history instead.
     */
    public function recordedPayments()
    {
        return $this->payments->where('status', '!=', 'rejected')->sortBy('id')->values();
    }

    /** The deposit taken when the booking was submitted — the first thing the agent filed. */
    public function originalDeposit(): ?Payment
    {
        return $this->recordedPayments()->first();
    }

    /** Everything collected after that first deposit. */
    public function additionalDepositsTotal(): float
    {
        return round((float) $this->recordedPayments()->skip(1)->sum('amount'), 2);
    }

    /**
     * Recorded ≠ verified. This is what the agent has filed; `paid_amount` is what staff
     * have confirmed, and only that drives balance() and commission.
     */
    public function recordedTotal(): float
    {
        return round((float) $this->recordedPayments()->sum('amount'), 2);
    }

    /** Outstanding against what has been filed, so the agent is not asked to collect twice. */
    public function outstandingRecorded(): float
    {
        if ($this->isDead()) {
            return 0.0;
        }

        return round(max(0, (float) $this->total_amount + (float) $this->forfeited_amount - $this->recordedTotal()), 2);
    }

    /** Filed but not yet checked by staff — the gap between the two totals above. */
    public function pendingVerificationTotal(): float
    {
        return round($this->recordedTotal() - (float) $this->paid_amount, 2);
    }

    /**
     * Label an incoming payment against what is still outstanding. Anything short of the
     * balance is an instalment — the first one is the deposit, the rest are partials.
     * Only a payment that clears the balance is a full settlement.
     */
    public function paymentTypeFor(float $amount): string
    {
        if ($amount < $this->balance() - 0.001) {
            return $this->payments()->exists() ? 'partial' : 'deposit';
        }

        return $this->paid_amount > 0 ? 'balance' : 'full';
    }

    /** Infants ride on a lap, not on a pack — they are never charged a cancellation fee. */
    public function chargeablePacks(): int
    {
        return (int) $this->adults + (int) $this->children + (int) $this->seniors;
    }

    /**
     * Paid, minus the penalty, minus whatever the trip still costs and whatever has
     * already been paid back. A cancelled booking owes nothing, so the whole remainder
     * is refundable. Never auto-paid — staff raise the refund.
     */
    public function refundableAmount(): float
    {
        $stillOwed = $this->isDead() ? 0.0 : (float) $this->total_amount;

        return round(max(0, $this->paidAfterForfeit() - $stillOwed - $this->refundedAmount()), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance() <= 0;
    }

    public function document(string $type): ?BookingDocument
    {
        return $this->documents->firstWhere('type', $type);
    }

    /** Everything the agent / customer / provider portals are allowed to list. */
    public function shareableDocuments()
    {
        return $this->documents->reject(fn ($doc) => $doc->isInternal())->values();
    }

    public function resortInvoice(): ?BookingDocument
    {
        return $this->document('resort_invoice');
    }
}
