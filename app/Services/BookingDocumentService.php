<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingDocumentService
{
    public function generateInvoice(Booking $booking): BookingDocument
    {
        return $this->render($booking, 'invoice', 'documents.invoice', 'Invoice-' . $booking->booking_no);
    }

    public function generateVoucher(Booking $booking): BookingDocument
    {
        return $this->render($booking, 'voucher', 'documents.voucher', 'Voucher-' . $booking->booking_no);
    }

    public function generateReceipt(Booking $booking): BookingDocument
    {
        return $this->render($booking, 'receipt', 'documents.receipt', 'Receipt-' . $booking->booking_no);
    }

    private function render(Booking $booking, string $type, string $view, string $filename): BookingDocument
    {
        $booking->loadMissing('package', 'customer', 'agent', 'provider', 'pax');
        $company = Company::current();

        $pdf = Pdf::loadView($view, ['booking' => $booking, 'company' => $company])->setPaper('a4');

        // PRIVATE disk — these hold pax names, passport numbers and prices. Served only
        // through BookingDocumentController, which checks ownership. The random suffix is
        // defence in depth so a leaked path cannot be walked to another booking.
        $path = "booking-docs/{$booking->id}/{$filename}-" . Str::random(12) . ".pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $existing = $booking->documents()->where('type', $type)->first();
        if ($existing) {
            if ($existing->file_path !== $path) {
                Storage::disk('local')->delete($existing->file_path);
            }
            $existing->update(['file_path' => $path, 'title' => $filename]);

            return $existing;
        }

        return $booking->documents()->create([
            'type'      => $type,
            'title'     => $filename,
            'file_path' => $path,
        ]);
    }
}
