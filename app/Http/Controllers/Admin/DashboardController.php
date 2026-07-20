<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $todayRevenue = (float) Payment::where('status', 'verified')->whereDate('verified_at', today())->sum('amount');

        $cards = [
            ['Pending Bookings', number_format(Booking::where('status', 'pending_verification')->count()), '⏳', 'warning', route('manage.bookings.index', ['status' => 'pending_verification'])],
            ['Pending Payments', number_format(Payment::where('status', 'pending')->count()), '💳', 'danger', route('manage.payments.index')],
            ['Pending Provider Confirmations', number_format(Booking::where('status', 'waiting_provider_confirmation')->count()), '🤝', 'info', route('manage.bookings.index', ['status' => 'waiting_provider_confirmation'])],
            ["Today's Bookings", number_format(Booking::whereDate('created_at', today())->count()), '📋', 'primary', route('manage.bookings.index')],
            ["Today's Revenue", 'RM ' . number_format($todayRevenue, 2), '💰', 'success', route('manage.finance.dashboard')],
            ['Open Tickets', number_format(Ticket::whereIn('status', ['open', 'pending'])->count()), '🎧', 'secondary', route('manage.tickets.index')],
        ];

        // The processing queue, oldest first — what admin should action next.
        $queue = Booking::with('customer', 'package', 'agent')
            ->whereIn('status', ['pending_verification', 'waiting_provider_confirmation'])
            ->oldest()->limit(10)->get();

        $unverifiedPayments = Payment::with('booking.customer')
            ->where('status', 'pending')->oldest()->limit(8)->get();

        $todayTravel = Booking::with('customer', 'package')
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereDate('travel_date', today())->get();

        return view('admin.dashboard', compact('cards', 'queue', 'unverifiedPayments', 'todayTravel'));
    }
}
