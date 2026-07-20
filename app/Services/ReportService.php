<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;

class ReportService
{
    const REPORTS = [
        'sales' => [
            'title' => 'Sales Report',
            'icon'  => '💵',
            'desc'  => 'Collected revenue over time, refunds and net position.',
        ],
        'bookings' => [
            'title' => 'Booking Report',
            'icon'  => '📋',
            'desc'  => 'Every booking with status, pax, value and outstanding balance.',
        ],
        'packages' => [
            'title' => 'Package Performance',
            'icon'  => '🗺️',
            'desc'  => 'Bookings, pax and revenue generated per package.',
        ],
        'customers' => [
            'title' => 'Customer Report',
            'icon'  => '👥',
            'desc'  => 'Customer database with spend, bookings and loyalty points.',
        ],
        'agents' => [
            'title' => 'Agent Performance',
            'icon'  => '🏅',
            'desc'  => 'Agent sales, bookings, commission earned and network size.',
        ],
        'commission' => [
            'title' => 'Commission Report',
            'icon'  => '💰',
            'desc'  => 'Commission ledger by level, earner, period and status.',
        ],
        'financial' => [
            'title' => 'Financial Summary',
            'icon'  => '📊',
            'desc'  => 'Money in, money out and the net position for the period.',
        ],
    ];

    public function build(string $key, array $filters = []): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->copy()->startOfMonth();
        $to   = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->copy()->endOfDay();

        $method = 'report' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        abort_unless(isset(self::REPORTS[$key]) && method_exists($this, $method), 404);

        $payload = $this->{$method}($from, $to, $filters);

        return array_merge([
            'key'      => $key,
            'title'    => self::REPORTS[$key]['title'],
            'icon'     => self::REPORTS[$key]['icon'],
            'from'     => $from,
            'to'       => $to,
            'filters'  => $filters,
            'kpis'     => [],
            'chart'    => null,
            'totals'   => [],
        ], $payload);
    }

    private function reportSales(Carbon $from, Carbon $to, array $filters): array
    {
        $group    = $filters['group'] ?? 'day';
        $payments = Payment::where('status', 'verified')->whereBetween('verified_at', [$from, $to])->get();
        $refunds  = Refund::where('status', 'processed')->whereBetween('processed_at', [$from, $to])->get();

        $fmt = $group === 'month' ? 'Y-m' : 'Y-m-d';
        $buckets = [];
        foreach ($payments as $p) {
            $k = $p->verified_at->format($fmt);
            $buckets[$k] = $buckets[$k] ?? ['count' => 0, 'gross' => 0.0, 'refund' => 0.0];
            $buckets[$k]['count']++;
            $buckets[$k]['gross'] += (float) $p->amount;
        }
        foreach ($refunds as $r) {
            $k = $r->processed_at->format($fmt);
            $buckets[$k] = $buckets[$k] ?? ['count' => 0, 'gross' => 0.0, 'refund' => 0.0];
            $buckets[$k]['refund'] += (float) $r->amount;
        }
        ksort($buckets);

        $rows = [];
        foreach ($buckets as $k => $b) {
            $label = $group === 'month' ? Carbon::parse($k . '-01')->format('M Y') : Carbon::parse($k)->format('d M Y');
            $rows[] = [$label, $b['count'], $b['gross'], $b['refund'], $b['gross'] - $b['refund']];
        }

        $gross      = (float) $payments->sum('amount');
        $refunded   = (float) $refunds->sum('amount');
        $bookingCnt = Booking::whereBetween('created_at', [$from, $to])->count();

        return [
            'subtitle' => 'Verified payments' . ($group === 'month' ? ' grouped by month' : ' grouped by day'),
            'columns'  => [
                ['label' => 'Period', 'format' => 'text'],
                ['label' => 'Payments', 'format' => 'int'],
                ['label' => 'Gross Collected', 'format' => 'money'],
                ['label' => 'Refunds', 'format' => 'money'],
                ['label' => 'Net', 'format' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => [0 => 'Total', 1 => $payments->count(), 2 => $gross, 3 => $refunded, 4 => $gross - $refunded],
            'kpis'   => [
                ['label' => 'Gross Collected', 'value' => 'RM ' . number_format($gross, 2), 'icon' => '💰', 'tone' => 'success'],
                ['label' => 'Refunded', 'value' => 'RM ' . number_format($refunded, 2), 'icon' => '↩️', 'tone' => 'danger'],
                ['label' => 'Net Revenue', 'value' => 'RM ' . number_format($gross - $refunded, 2), 'icon' => '📈', 'tone' => 'primary'],
                ['label' => 'New Bookings', 'value' => number_format($bookingCnt), 'icon' => '📋', 'tone' => 'info'],
            ],
            'chart' => [
                'title'  => 'Net revenue',
                'series' => array_map(fn ($r) => ['label' => $r[0], 'value' => $r[4]], array_slice($rows, -12)),
            ],
        ];
    }

    private function reportBookings(Carbon $from, Carbon $to, array $filters): array
    {
        $query = Booking::with('customer', 'package', 'agent')->whereBetween('created_at', [$from, $to]);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        $bookings = $query->latest()->get();

        $rows = [];
        foreach ($bookings as $b) {
            $rows[] = [
                $b->booking_no,
                $b->created_at->format('d M Y'),
                $b->customer?->name ?? '—',
                $b->package?->title ?? '—',
                $b->agent?->name ?? 'Direct',
                Booking::TYPES[$b->type] ?? $b->type,
                Booking::STATUSES[$b->status] ?? $b->status,
                (int) $b->total_pax,
                (float) $b->total_amount,
                (float) $b->paid_amount,
                max(0, (float) $b->total_amount - (float) $b->paid_amount),
            ];
        }

        $live = $bookings->whereNotIn('status', ['cancelled', 'rejected', 'draft']);

        return [
            'subtitle' => 'Bookings created in the selected period',
            'columns'  => [
                ['label' => 'Booking No', 'format' => 'text'],
                ['label' => 'Date', 'format' => 'text'],
                ['label' => 'Customer', 'format' => 'text'],
                ['label' => 'Package', 'format' => 'text'],
                ['label' => 'Agent', 'format' => 'text'],
                ['label' => 'Type', 'format' => 'text'],
                ['label' => 'Status', 'format' => 'text'],
                ['label' => 'Pax', 'format' => 'int'],
                ['label' => 'Total', 'format' => 'money'],
                ['label' => 'Paid', 'format' => 'money'],
                ['label' => 'Balance', 'format' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => [
                0 => 'Total (' . $bookings->count() . ')',
                7 => (int) $bookings->sum('total_pax'),
                8 => (float) $bookings->sum('total_amount'),
                9 => (float) $bookings->sum('paid_amount'),
                10 => (float) $bookings->sum(fn ($b) => max(0, (float) $b->total_amount - (float) $b->paid_amount)),
            ],
            'kpis' => [
                ['label' => 'Bookings', 'value' => number_format($bookings->count()), 'icon' => '📋', 'tone' => 'primary'],
                ['label' => 'Confirmed', 'value' => number_format($bookings->whereIn('status', ['confirmed', 'completed'])->count()), 'icon' => '✅', 'tone' => 'success'],
                ['label' => 'Total Value', 'value' => 'RM ' . number_format((float) $live->sum('total_amount'), 2), 'icon' => '💵', 'tone' => 'info'],
                ['label' => 'Total Pax', 'value' => number_format((int) $bookings->sum('total_pax')), 'icon' => '🧳', 'tone' => 'warning'],
            ],
            'chart' => [
                'title'  => 'Bookings by status',
                'series' => collect(Booking::STATUSES)
                    ->map(fn ($label, $k) => ['label' => $label, 'value' => $bookings->where('status', $k)->count()])
                    ->filter(fn ($s) => $s['value'] > 0)->values()->all(),
            ],
        ];
    }

    private function reportPackages(Carbon $from, Carbon $to, array $filters): array
    {
        $packages = Package::with('provider')->get();
        $bookings = Booking::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->get()->groupBy('package_id');

        $rows = [];
        foreach ($packages as $p) {
            $set = $bookings[$p->id] ?? collect();
            if (! empty($filters['category']) && $p->category !== $filters['category']) {
                continue;
            }
            $rows[] = [
                $p->code,
                $p->title,
                $p->categoryLabel(),
                $p->provider?->name ?? '—',
                $set->count(),
                (int) $set->sum('total_pax'),
                (float) $set->sum('total_amount'),
                (float) $set->sum('paid_amount'),
            ];
        }
        usort($rows, fn ($a, $b) => $b[6] <=> $a[6]);

        $sold = array_values(array_filter($rows, fn ($r) => $r[4] > 0));

        return [
            'subtitle' => 'Revenue attributed to bookings created in the period (cancelled/rejected excluded)',
            'columns'  => [
                ['label' => 'Code', 'format' => 'text'],
                ['label' => 'Package', 'format' => 'text'],
                ['label' => 'Category', 'format' => 'text'],
                ['label' => 'Provider', 'format' => 'text'],
                ['label' => 'Bookings', 'format' => 'int'],
                ['label' => 'Pax', 'format' => 'int'],
                ['label' => 'Revenue', 'format' => 'money'],
                ['label' => 'Collected', 'format' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => [
                0 => 'Total',
                4 => array_sum(array_column($rows, 4)),
                5 => array_sum(array_column($rows, 5)),
                6 => array_sum(array_column($rows, 6)),
                7 => array_sum(array_column($rows, 7)),
            ],
            'kpis' => [
                ['label' => 'Packages', 'value' => number_format(count($rows)), 'icon' => '🗺️', 'tone' => 'primary'],
                ['label' => 'Packages Sold', 'value' => number_format(count($sold)), 'icon' => '🔥', 'tone' => 'success'],
                ['label' => 'Revenue', 'value' => 'RM ' . number_format(array_sum(array_column($rows, 6)), 2), 'icon' => '💵', 'tone' => 'info'],
                ['label' => 'Top Seller', 'value' => $sold[0][1] ?? '—', 'icon' => '🏆', 'tone' => 'warning'],
            ],
            'chart' => [
                'title'  => 'Top packages by revenue',
                'series' => array_map(fn ($r) => ['label' => $r[0], 'value' => $r[6]], array_slice($sold, 0, 8)),
            ],
        ];
    }

    private function reportCustomers(Carbon $from, Carbon $to, array $filters): array
    {
        $customers = Customer::with('agent')->get();
        $bookings  = Booking::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->get()->groupBy('customer_id');

        $rows = [];
        foreach ($customers as $c) {
            $set = $bookings[$c->id] ?? collect();
            if (! empty($filters['active_only']) && $set->count() === 0) {
                continue;
            }
            $last = $set->sortByDesc('created_at')->first();
            $rows[] = [
                $c->name,
                $c->phone ?: '—',
                $c->email ?: '—',
                $c->agent?->name ?? '—',
                $set->count(),
                (int) $set->sum('total_pax'),
                (float) $set->sum('total_amount'),
                (int) $c->loyalty_points,
                $last ? $last->created_at->format('d M Y') : '—',
            ];
        }
        usort($rows, fn ($a, $b) => $b[6] <=> $a[6]);

        $buyers = array_filter($rows, fn ($r) => $r[4] > 0);
        $repeat = array_filter($rows, fn ($r) => $r[4] > 1);

        return [
            'subtitle' => 'Customer database with spend recorded in the selected period',
            'columns'  => [
                ['label' => 'Customer', 'format' => 'text'],
                ['label' => 'Phone', 'format' => 'text'],
                ['label' => 'Email', 'format' => 'text'],
                ['label' => 'Agent', 'format' => 'text'],
                ['label' => 'Bookings', 'format' => 'int'],
                ['label' => 'Pax', 'format' => 'int'],
                ['label' => 'Spend', 'format' => 'money'],
                ['label' => 'Loyalty Pts', 'format' => 'int'],
                ['label' => 'Last Booking', 'format' => 'text'],
            ],
            'rows'   => $rows,
            'totals' => [
                0 => 'Total (' . count($rows) . ')',
                4 => array_sum(array_column($rows, 4)),
                5 => array_sum(array_column($rows, 5)),
                6 => array_sum(array_column($rows, 6)),
                7 => array_sum(array_column($rows, 7)),
            ],
            'kpis' => [
                ['label' => 'Customers', 'value' => number_format(count($rows)), 'icon' => '👥', 'tone' => 'primary'],
                ['label' => 'Buying Customers', 'value' => number_format(count($buyers)), 'icon' => '🛒', 'tone' => 'success'],
                ['label' => 'Repeat Buyers', 'value' => number_format(count($repeat)), 'icon' => '🔁', 'tone' => 'info'],
                ['label' => 'Total Spend', 'value' => 'RM ' . number_format(array_sum(array_column($rows, 6)), 2), 'icon' => '💵', 'tone' => 'warning'],
            ],
            'chart' => [
                'title'  => 'Top customers by spend',
                'series' => array_map(fn ($r) => ['label' => $r[0], 'value' => $r[6]], array_slice(array_values($buyers), 0, 8)),
            ],
        ];
    }

    private function reportAgents(Carbon $from, Carbon $to, array $filters): array
    {
        $agents      = User::where('role', 'agent')->get();
        $bookings    = Booking::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->get()->groupBy('agent_id');
        $commissions = Commission::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'reversed')->get()->groupBy('earner_id');
        $downlines   = User::where('role', 'agent')->whereNotNull('referrer_id')
            ->get()->groupBy('referrer_id');

        $rows = [];
        foreach ($agents as $a) {
            $set  = $bookings[$a->id] ?? collect();
            $comm = $commissions[$a->id] ?? collect();
            $rows[] = [
                $a->agent_code,
                $a->name,
                ucfirst((string) $a->agent_tier),
                $set->count(),
                (int) $set->sum('total_pax'),
                (float) $set->sum('total_amount'),
                (float) $set->sum('paid_amount'),
                (float) $comm->sum('amount'),
                count($downlines[$a->id] ?? []),
                (int) $a->reward_points,
            ];
        }
        usort($rows, fn ($a, $b) => $b[5] <=> $a[5]);

        $active = array_values(array_filter($rows, fn ($r) => $r[3] > 0));

        return [
            'subtitle' => 'Agent sales and commission earned in the selected period',
            'columns'  => [
                ['label' => 'Code', 'format' => 'text'],
                ['label' => 'Agent', 'format' => 'text'],
                ['label' => 'Tier', 'format' => 'text'],
                ['label' => 'Bookings', 'format' => 'int'],
                ['label' => 'Pax', 'format' => 'int'],
                ['label' => 'Sales', 'format' => 'money'],
                ['label' => 'Collected', 'format' => 'money'],
                ['label' => 'Commission', 'format' => 'money'],
                ['label' => 'Downlines', 'format' => 'int'],
                ['label' => 'Points', 'format' => 'int'],
            ],
            'rows'   => $rows,
            'totals' => [
                0 => 'Total',
                3 => array_sum(array_column($rows, 3)),
                4 => array_sum(array_column($rows, 4)),
                5 => array_sum(array_column($rows, 5)),
                6 => array_sum(array_column($rows, 6)),
                7 => array_sum(array_column($rows, 7)),
                8 => array_sum(array_column($rows, 8)),
                9 => array_sum(array_column($rows, 9)),
            ],
            'kpis' => [
                ['label' => 'Agents', 'value' => number_format(count($rows)), 'icon' => '🧑‍💼', 'tone' => 'primary'],
                ['label' => 'Producing Agents', 'value' => number_format(count($active)), 'icon' => '🔥', 'tone' => 'success'],
                ['label' => 'Total Sales', 'value' => 'RM ' . number_format(array_sum(array_column($rows, 5)), 2), 'icon' => '💵', 'tone' => 'info'],
                ['label' => 'Top Agent', 'value' => $active[0][1] ?? '—', 'icon' => '🏆', 'tone' => 'warning'],
            ],
            'chart' => [
                'title'  => 'Top agents by sales',
                'series' => array_map(fn ($r) => ['label' => $r[0], 'value' => $r[5]], array_slice($active, 0, 8)),
            ],
        ];
    }

    private function reportCommission(Carbon $from, Carbon $to, array $filters): array
    {
        $query = Commission::with('booking', 'earner', 'sourceAgent')->whereBetween('created_at', [$from, $to]);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        $commissions = $query->latest()->get();

        $rows = [];
        foreach ($commissions as $c) {
            $rows[] = [
                $c->created_at->format('d M Y'),
                $c->period,
                $c->booking?->booking_no ?? '—',
                $c->is_orphan ? 'HQ (orphan)' : ($c->earner?->name ?? '—'),
                $c->sourceAgent?->name ?? '—',
                'L' . $c->level,
                (float) $c->base_amount,
                (float) $c->percent,
                (float) $c->amount,
                ucfirst($c->status),
            ];
        }

        $byStatus = fn ($s) => (float) $commissions->where('status', $s)->sum('amount');

        return [
            'subtitle' => 'Commission entries generated in the selected period',
            'columns'  => [
                ['label' => 'Date', 'format' => 'text'],
                ['label' => 'Period', 'format' => 'text'],
                ['label' => 'Booking', 'format' => 'text'],
                ['label' => 'Earner', 'format' => 'text'],
                ['label' => 'Source Agent', 'format' => 'text'],
                ['label' => 'Level', 'format' => 'text'],
                ['label' => 'Base', 'format' => 'money'],
                ['label' => '%', 'format' => 'percent'],
                ['label' => 'Amount', 'format' => 'money'],
                ['label' => 'Status', 'format' => 'text'],
            ],
            'rows'   => $rows,
            'totals' => [
                0 => 'Total (' . $commissions->count() . ')',
                6 => (float) $commissions->sum('base_amount'),
                8 => (float) $commissions->sum('amount'),
            ],
            'kpis' => [
                ['label' => 'Pending', 'value' => 'RM ' . number_format($byStatus('pending'), 2), 'icon' => '⏳', 'tone' => 'warning'],
                ['label' => 'Approved', 'value' => 'RM ' . number_format($byStatus('approved'), 2), 'icon' => '✅', 'tone' => 'info'],
                ['label' => 'Paid', 'value' => 'RM ' . number_format($byStatus('paid'), 2), 'icon' => '💸', 'tone' => 'success'],
                ['label' => 'Orphan → HQ', 'value' => 'RM ' . number_format((float) $commissions->where('is_orphan', true)->sum('amount'), 2), 'icon' => '🏢', 'tone' => 'secondary'],
            ],
            'chart' => [
                'title'  => 'Commission by level',
                'series' => $commissions->groupBy('level')->sortKeys()
                    ->map(fn ($g, $lvl) => ['label' => 'Level ' . $lvl, 'value' => (float) $g->sum('amount')])
                    ->values()->all(),
            ],
        ];
    }

    private function reportFinancial(Carbon $from, Carbon $to, array $filters): array
    {
        $collected   = (float) Payment::where('status', 'verified')->whereBetween('verified_at', [$from, $to])->sum('amount');
        $pendingPay  = (float) Payment::where('status', 'pending')->whereBetween('created_at', [$from, $to])->sum('amount');
        $refunded    = (float) Refund::where('status', 'processed')->whereBetween('processed_at', [$from, $to])->sum('amount');
        $commApprove = (float) Commission::whereIn('status', ['approved', 'paid'])->whereBetween('created_at', [$from, $to])->sum('amount');
        $commPending = (float) Commission::where('status', 'pending')->whereBetween('created_at', [$from, $to])->sum('amount');
        $withdrawn   = (float) Withdrawal::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('amount');

        $booked      = Booking::whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled', 'rejected', 'draft'])->get();
        $bookedValue = (float) $booked->sum('total_amount');
        $outstanding = (float) $booked->sum(fn ($b) => max(0, (float) $b->total_amount - (float) $b->paid_amount));
        $discount    = (float) $booked->sum('discount');
        $net         = $collected - $refunded - $withdrawn;

        $rows = [
            ['Money In', 'Payments verified', $collected],
            ['Money In', 'Payments pending verification', $pendingPay],
            ['Money In', 'Booked value (contracted)', $bookedValue],
            ['Money In', 'Outstanding balance receivable', $outstanding],
            ['Deductions', 'Coupon / promo discount given', -$discount],
            ['Money Out', 'Refunds processed', -$refunded],
            ['Money Out', 'Commission approved & paid', -$commApprove],
            ['Money Out', 'Commission pending approval', -$commPending],
            ['Money Out', 'Withdrawals paid to agents', -$withdrawn],
            ['Net', 'Net cash position (collected − refunds − withdrawals)', $net],
        ];

        return [
            'subtitle' => 'Money in, money out and net cash position for the period',
            'columns'  => [
                ['label' => 'Group', 'format' => 'text'],
                ['label' => 'Line Item', 'format' => 'text'],
                ['label' => 'Amount', 'format' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => [],
            'kpis'   => [
                ['label' => 'Collected', 'value' => 'RM ' . number_format($collected, 2), 'icon' => '💰', 'tone' => 'success'],
                ['label' => 'Outstanding', 'value' => 'RM ' . number_format($outstanding, 2), 'icon' => '⏳', 'tone' => 'warning'],
                ['label' => 'Paid Out', 'value' => 'RM ' . number_format($refunded + $withdrawn, 2), 'icon' => '↩️', 'tone' => 'danger'],
                ['label' => 'Net Position', 'value' => 'RM ' . number_format($net, 2), 'icon' => '📊', 'tone' => 'primary'],
            ],
            'chart' => [
                'title'  => 'Cash movement',
                'series' => [
                    ['label' => 'Collected', 'value' => $collected],
                    ['label' => 'Refunds', 'value' => $refunded],
                    ['label' => 'Commission', 'value' => $commApprove],
                    ['label' => 'Withdrawals', 'value' => $withdrawn],
                    ['label' => 'Outstanding', 'value' => $outstanding],
                ],
            ],
        ];
    }
}
