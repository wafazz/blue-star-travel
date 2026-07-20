<?php

namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Commission;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\LeaderboardService;

class DashboardController extends Controller
{
    public function __construct(private LeaderboardService $leaderboard) {}

    public function index()
    {
        $dead = ['cancelled', 'rejected', 'draft'];

        $salesToday = (float) Payment::where('status', 'verified')->whereDate('verified_at', today())->sum('amount');
        $salesMonth = (float) Payment::where('status', 'verified')
            ->whereMonth('verified_at', now()->month)->whereYear('verified_at', now()->year)->sum('amount');

        $pendingPayAmt = (float) Payment::where('status', 'pending')->sum('amount');
        $commissionDue = (float) Commission::whereIn('status', ['pending', 'approved'])->sum('amount');

        $kpis = [
            ['Total Sales Today', 'RM ' . number_format($salesToday, 2), '📅', 'primary'],
            ['Monthly Sales', 'RM ' . number_format($salesMonth, 2), '📈', 'success'],
            ['Total Bookings', number_format(Booking::count()), '📋', 'info'],
            ['Pending Bookings', number_format(Booking::whereIn('status', ['pending_verification', 'pending_payment', 'waiting_provider_confirmation'])->count()), '⏳', 'warning'],
            ['Confirmed Bookings', number_format(Booking::whereIn('status', ['confirmed', 'completed'])->count()), '✅', 'success'],
            ['Pending Payments', 'RM ' . number_format($pendingPayAmt, 2), '💳', 'danger'],
            ['Active Agents', number_format(User::where('role', 'agent')->where('status', 'active')->count()), '🧑‍💼', 'primary'],
            ['Commission Payable', 'RM ' . number_format($commissionDue, 2), '💰', 'warning'],
        ];

        $topAgents = $this->leaderboard->monthly()->take(5);

        $topPackages = Package::withCount(['bookings' => fn ($q) => $q->whereNotIn('status', $dead)])
            ->orderByDesc('bookings_count')->limit(5)->get();

        $attention = [
            ['Bookings to verify', Booking::where('status', 'pending_verification')->count(), route('manage.bookings.index', ['status' => 'pending_verification'])],
            ['Payments to verify', Payment::where('status', 'pending')->count(), route('manage.payments.index')],
            ['Awaiting provider', Booking::where('status', 'waiting_provider_confirmation')->count(), route('manage.bookings.index', ['status' => 'waiting_provider_confirmation'])],
            ['Commissions pending approval', Commission::where('status', 'pending')->count(), route('manage.commission.index')],
            ['Withdrawals pending', Withdrawal::where('status', 'pending')->count(), route('manage.withdrawals.index')],
        ];

        $recent = Booking::with('customer', 'package', 'agent')->latest()->limit(6)->get();

        // 6-month revenue trend (verified payments) — same CSS-bar treatment as Finance.
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $trend[] = [
                'label' => $month->format('M'),
                'value' => (float) Payment::where('status', 'verified')
                    ->whereMonth('verified_at', $month->month)->whereYear('verified_at', $month->year)->sum('amount'),
            ];
        }
        $trendMax = max(1, ...array_map(fn ($t) => $t['value'], $trend));

        return view('hq.dashboard', compact('kpis', 'topAgents', 'topPackages', 'attention', 'recent', 'trend', 'trendMax'));
    }
}
