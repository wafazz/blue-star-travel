<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    private function providerId(Request $request): ?int
    {
        return optional($request->user()->provider)->id;
    }

    /**
     * Bookings can have a null provider_id (package with no provider assigned), and a
     * provider user can have no linked Provider row — so `null === null` would grant
     * access to every unassigned booking. Require a real id on both sides.
     */
    private function assertOwns(Booking $booking, Request $request): void
    {
        $providerId = $this->providerId($request);
        abort_unless($providerId !== null && $booking->provider_id === $providerId, 403);
    }

    public function index(Request $request)
    {
        // `where('provider_id', null)` compiles to `IS NULL`, which would list every
        // booking whose package has no provider assigned. A provider user with no
        // linked Provider row owns nothing at all.
        $providerId = $this->providerId($request);
        if ($providerId === null) {
            abort(403, 'This account is not linked to a service provider.');
        }

        $query = Booking::query()->with('package', 'customer')->where('provider_id', $providerId);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        $pending = Booking::where('provider_id', $providerId)
            ->where('status', 'waiting_provider_confirmation')->count();

        return view('provider.bookings.index', compact('bookings', 'pending'));
    }

    public function show(Booking $booking, Request $request)
    {
        $this->assertOwns($booking, $request);
        $booking->load('package', 'customer', 'packageDate', 'pricing', 'pax', 'timeline.user');

        return view('provider.bookings.show', compact('booking'));
    }

    public function respond(Booking $booking, Request $request)
    {
        $this->assertOwns($booking, $request);
        abort_unless($booking->status === 'waiting_provider_confirmation', 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookings->providerRespond($booking, $request->user(), $data['decision'], $data['note'] ?? null);

        return back()->with('ok', 'Response submitted to head office.');
    }
}
