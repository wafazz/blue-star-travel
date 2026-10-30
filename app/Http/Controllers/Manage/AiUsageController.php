<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Assistant\UsageReport;
use Illuminate\Http\Request;

/** Token and cost usage for HQ (REQ-AI-022). */
class AiUsageController extends Controller
{
    private const PERIODS = [7, 30, 90];

    public function index(Request $request, UsageReport $report)
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, self::PERIODS, true)) {
            $days = 30;
        }

        return view('manage.ai-assistant.usage', $report->summary($days) + ['periods' => self::PERIODS]);
    }
}
