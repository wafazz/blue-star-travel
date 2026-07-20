<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $customer = $user->customerProfile;

        $bookings = $customer
            ? Booking::with('package')->where('customer_id', $customer->id)->latest()->get()
            : collect();

        $live = $bookings->whereNotIn('status', ['cancelled', 'rejected', 'draft']);

        $stats = [
            'trips'       => $live->count(),
            'upcoming'    => $live->whereIn('status', ['confirmed', 'waiting_provider_confirmation'])->count(),
            'spend'       => (float) $live->sum('paid_amount'),
            'outstanding' => (float) $live->sum(fn ($b) => max(0, $b->balance())),
        ];

        $recent   = $bookings->take(3);
        $featured = Package::with('pricings')->where('status', 'active')->orderByDesc('featured')->limit(3)->get();
        $banner   = Banner::live('customer')->first();

        return view('customer.dashboard', compact('customer', 'stats', 'recent', 'featured', 'banner'));
    }
}
