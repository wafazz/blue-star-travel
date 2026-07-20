<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\MarketingMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingController extends Controller
{
    public function index()
    {
        $materials = MarketingMaterial::where('active', true)->latest()->get();

        return view('agent.marketing.index', compact('materials'));
    }

    public function download(MarketingMaterial $material)
    {
        abort_unless($material->active, 404);
        $material->increment('downloads_count');

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            return Storage::disk('public')->download($material->file_path);
        }
        if ($material->external_url) {
            return redirect($material->external_url);
        }

        return back()->with('ok', 'Material has no downloadable file.');
    }
}
