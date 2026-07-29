<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingDocumentController extends Controller
{
    public function download(BookingDocument $document, Request $request)
    {
        $document->load('booking');
        abort_unless($this->canSee($document->booking, $request), 403);
        // Internal paperwork is staff-only even for the agent who owns the booking.
        abort_if($document->isInternal() && ! $request->user()->isStaff(), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        // Uploaded documents may be images, so the extension comes off the stored file.
        $ext = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->download($document->file_path, $document->title . '.' . $ext);
    }

    /** Payment slips hold personal banking data — same ownership rules as documents. */
    public function slip(Payment $payment, Request $request)
    {
        $payment->load('booking');
        abort_unless($this->canSee($payment->booking, $request), 403);
        abort_unless($payment->slip_path && Storage::disk('local')->exists($payment->slip_path), 404);

        return Storage::disk('local')->response($payment->slip_path);
    }

    private function canSee(?Booking $booking, Request $request): bool
    {
        if (! $booking) {
            return false;
        }

        $user = $request->user();
        $providerId = optional($user->provider)->id;

        return $user->isStaff()
            || ($user->hasRole('agent') && $booking->agent_id === $user->id)
            || ($user->hasRole('provider') && $providerId !== null && $booking->provider_id === $providerId)
            || ($user->hasRole('customer') && $booking->customer && $booking->customer->user_id === $user->id);
    }
}
