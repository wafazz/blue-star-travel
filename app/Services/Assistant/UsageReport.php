<?php

namespace App\Services\Assistant;

use App\Models\AssistantMessage;
use Illuminate\Support\Facades\DB;

/**
 * Token and cost totals for the admin usage page (REQ-AI-022, TASK-AI-032).
 * Cost is shown only for providers whose prices HQ has entered. It is never guessed.
 */
class UsageReport
{
    public function __construct(private readonly AssistantSettings $settings) {}

    /**
     * @return array{days:int, providers: array<int, array<string,mixed>>, totals: array<string,mixed>, daily: array<int, array<string,mixed>>}
     */
    public function summary(int $days): array
    {
        $since = now()->subDays($days)->startOfDay();

        // Only model calls carry a provider: visitor replies and staff drafts both cost money.
        $base = AssistantMessage::query()
            ->where('role', 'assistant')
            ->whereNotNull('provider')
            ->where('created_at', '>=', $since);

        $rows = (clone $base)
            ->select('provider', DB::raw('COUNT(*) as calls'), DB::raw('SUM(input_tokens) as input_tokens'), DB::raw('SUM(output_tokens) as output_tokens'))
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        $providers = [];
        $totalCost = 0.0;
        $costKnownForAll = true;

        foreach (AssistantSettings::PROVIDERS as $key) {
            $row = $rows->get($key);
            $in = (int) ($row->input_tokens ?? 0);
            $out = (int) ($row->output_tokens ?? 0);

            $priceIn = $this->settings->priceIn($key);
            $priceOut = $this->settings->priceOut($key);
            $cost = ($priceIn !== null && $priceOut !== null)
                ? ($in * $priceIn + $out * $priceOut) / 1_000_000
                : null;

            if ($row && $cost === null) {
                $costKnownForAll = false;
            }
            $totalCost += $cost ?? 0.0;

            $providers[] = [
                'key' => $key,
                'label' => config("ai.drivers.{$key}.label"),
                'calls' => (int) ($row->calls ?? 0),
                'input_tokens' => $in,
                'output_tokens' => $out,
                'cost' => $cost,
                'has_prices' => $priceIn !== null && $priceOut !== null,
            ];
        }

        $daily = (clone $base)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as calls'), DB::raw('SUM(input_tokens + output_tokens) as tokens'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => (string) $r->day, 'calls' => (int) $r->calls, 'tokens' => (int) $r->tokens])
            ->all();

        return [
            'days' => $days,
            'providers' => $providers,
            'daily' => $daily,
            'totals' => [
                'calls' => array_sum(array_column($providers, 'calls')),
                'input_tokens' => array_sum(array_column($providers, 'input_tokens')),
                'output_tokens' => array_sum(array_column($providers, 'output_tokens')),
                // Total cost is shown only when every provider with usage has prices.
                'cost' => $costKnownForAll ? round($totalCost, 4) : null,
            ],
        ];
    }
}
