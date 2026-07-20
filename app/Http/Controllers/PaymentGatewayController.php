<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private PaymentGatewayService $gateway,
        private PaymentGatewayManager $gateways,
    ) {}

    /**
     * The simulated bank screen must never be reachable in production, nor when a
     * real vendor is configured.
     */
    private function abortIfNotSandbox(): void
    {
        abort_if(app()->environment('production') || $this->gateways->isLive(), 404);
    }

    /** An unrecognised driver in the URL is a 404, not a 500. */
    private function resolveDriver(string $driverKey)
    {
        try {
            return $this->gateways->driver($driverKey);
        } catch (Throwable $e) {
            abort(404);
        }
    }

    private function authorizeBooking(Booking $booking, Request $request): void
    {
        $user = $request->user();
        $ok = $user->isStaff()
            || ($user->hasRole('agent') && $booking->agent_id === $user->id)
            || ($user->hasRole('customer') && $booking->customer && $booking->customer->user_id === $user->id);
        abort_unless($ok, 403);
    }

    private function returnUrl(Booking $booking, Request $request): string
    {
        $user = $request->user();
        if ($user->hasRole('agent')) {
            return route('agent.bookings.show', $booking);
        }
        if ($user->hasRole('customer')) {
            return route('customer.bookings.show', $booking);
        }

        return route('manage.bookings.show', $booking);
    }

    public function initiate(Booking $booking, Request $request)
    {
        $this->authorizeBooking($booking, $request);
        abort_if(in_array($booking->status, ['cancelled', 'rejected']), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . max(0.01, $booking->balance())],
        ]);

        $payment = $this->gateway->initiate($booking, (float) $data['amount'], $request->user());
        session(['gateway_return' => $this->returnUrl($booking, $request)]);

        try {
            $url = $this->gateway->driver()->start(
                $payment->fresh()->load('booking.customer', 'booking.package'),
                route('gateway.webhook', $this->gateways->defaultKey()),
                route('gateway.return', $this->gateways->defaultKey()),
            );
        } catch (Throwable $e) {
            Log::error('Payment start failed', ['booking' => $booking->booking_no, 'error' => $e->getMessage()]);
            $payment->update(['status' => 'rejected', 'note' => 'Could not reach the payment gateway']);

            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }

    /**
     * Vendor webhook — server to server, no session. CSRF is exempt (there is no
     * browser here), so the SIGNATURE is the only authentication: an unsigned or
     * mismatched payload is dropped before anything is credited.
     */
    public function webhook(string $driverKey, Request $request)
    {
        $driver  = $this->resolveDriver($driverKey);
        $payload = $request->all();

        if (! $driver->verifySignature($payload)) {
            Log::warning('Rejected payment webhook — bad signature', ['driver' => $driverKey, 'ip' => $request->ip()]);

            return response('invalid signature', 403);
        }

        $ref = $driver->referenceFrom($payload);
        $payment = $ref ? Payment::with('booking')->where('gateway_ref', $ref)->first() : null;
        if (! $payment) {
            return response('unknown payment', 404);
        }

        // Re-query the vendor rather than trusting the posted amount.
        $this->gateway->sync($payment);

        return response('ok', 200);
    }

    /**
     * Where the payer lands after paying. Signed too, but treated only as a cue to
     * re-check with the vendor — the webhook remains the source of truth.
     */
    public function return(string $driverKey, Request $request)
    {
        $driver  = $this->resolveDriver($driverKey);
        $payload = $request->all();
        $ref     = $driver->referenceFrom($payload, true);

        $payment = $ref ? Payment::with('booking')->where('gateway_ref', $ref)->first() : null;

        if ($payment && $driver->verifySignature($payload, true)) {
            $payment = $this->gateway->sync($payment, $request->user());
        }

        $fallback = $payment?->booking ? route('manage.bookings.show', $payment->booking) : route('home');
        $url = session()->pull('gateway_return', $fallback);

        $msg = match ($payment?->status) {
            'verified' => 'Payment received — thank you! Your booking has been updated.',
            'rejected' => 'The payment was not completed.',
            default    => 'We are confirming your payment with the bank. This page will update once it clears.',
        };

        return redirect($url)->with('ok', $msg);
    }

    // ---- Sandbox simulator (dev only) ------------------------------------

    public function checkout(string $ref, Request $request)
    {
        $this->abortIfNotSandbox();
        $payment = Payment::with('booking.customer')->where('gateway_ref', $ref)->firstOrFail();
        $this->authorizeBooking($payment->booking, $request);
        abort_unless($payment->status === 'pending', 404);

        return view('gateway.checkout', [
            'payment' => $payment,
            'banks'   => PaymentGatewayService::BANKS,
        ]);
    }

    public function callback(string $ref, Request $request)
    {
        $this->abortIfNotSandbox();
        $payment = Payment::with('booking')->where('gateway_ref', $ref)->firstOrFail();
        $this->authorizeBooking($payment->booking, $request);

        $data = $request->validate([
            'result' => ['required', 'in:success,fail'],
            'bank'   => ['nullable', 'string', 'max:100'],
        ]);

        $this->gateway->handleSandboxCallback($ref, $data['result'] === 'success', $data['bank'] ?? '', $request->user());

        $url = session()->pull('gateway_return', route('manage.bookings.show', $payment->booking));
        $msg = $data['result'] === 'success'
            ? 'FPX authorisation received (' . $ref . ') — payment is pending verification by our team.'
            : 'Payment was not completed.';

        return redirect($url)->with('ok', $msg);
    }
}
