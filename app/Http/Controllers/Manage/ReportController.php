<?php

namespace App\Http\Controllers\Manage;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index()
    {
        $reports = ReportService::REPORTS;

        return view('manage.reports.index', compact('reports'));
    }

    public function show(string $key, Request $request)
    {
        $report = $this->reports->build($key, $this->filters($request));

        return view('manage.reports.show', compact('report'));
    }

    public function export(string $key, string $format, Request $request)
    {
        abort_unless(in_array($format, ['csv', 'excel', 'pdf'], true), 404);

        $report   = $this->reports->build($key, $this->filters($request));
        $filename = $key . '-report-' . $report['from']->format('Ymd') . '-' . $report['to']->format('Ymd');

        if ($format === 'pdf') {
            $company = Company::current();

            return Pdf::loadView('documents.report', compact('report', 'company'))
                ->setPaper('a4', count($report['columns']) > 7 ? 'landscape' : 'portrait')
                ->download($filename . '.pdf');
        }

        $export = new ReportExport($report);

        if ($format === 'csv') {
            return Excel::download($export, $filename . '.csv', ExcelWriter::CSV);
        }

        return Excel::download($export, $filename . '.xlsx', ExcelWriter::XLSX);
    }

    private function filters(Request $request): array
    {
        return array_filter($request->only([
            'from', 'to', 'group', 'status', 'type', 'category', 'level', 'active_only',
        ]), fn ($v) => $v !== null && $v !== '');
    }
}
