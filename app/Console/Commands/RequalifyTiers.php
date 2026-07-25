<?php

namespace App\Console\Commands;

use App\Services\TierService;
use Illuminate\Console\Command;

class RequalifyTiers extends Command
{
    protected $signature = 'tiers:requalify';

    protected $description = 'Apply period-based tier re-qualification (promotions + demotions) when a qualification period rolls over';

    public function handle(TierService $tiers): int
    {
        $roll = $tiers->processPeriodRollover();

        if (! $roll['ran']) {
            $this->info('No period rollover due — tiers unchanged.');

            return self::SUCCESS;
        }

        $this->info("Period {$roll['period']} maintenance applied: {$roll['demoted']} demoted, {$roll['promoted']} re-qualified up.");

        return self::SUCCESS;
    }
}
