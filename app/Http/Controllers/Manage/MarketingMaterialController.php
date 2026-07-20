<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\MarketingMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingMaterialController extends Controller
{
    public function index()
    {
        $materials = MarketingMaterial::latest()->paginate(15);

        return view('manage.materials.index', compact('materials'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('marketing', 'public');
        }
        MarketingMaterial::create($data);

        return back()->with('ok', 'Material added.');
    }

    public function update(MarketingMaterial $material, Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('file')) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $data['file_path'] = $request->file('file')->store('marketing', 'public');
        }
        $material->update($data);

        return back()->with('ok', 'Material updated.');
    }

    public function destroy(MarketingMaterial $material)
    {
        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }
        $material->delete();

        return back()->with('ok', 'Material deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:255'],
            'category'     => ['required', 'in:' . implode(',', array_keys(MarketingMaterial::CATEGORIES))],
            // Allow-list only — this disk is web-served, so an unrestricted upload
            // would let a staff account drop an executable script into the docroot.
            'file'         => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,mp4,mov,zip,doc,docx,ppt,pptx'],
            'external_url' => ['nullable', 'string', 'max:255'],
            'active'       => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        unset($data['file']);

        return $data;
    }
}
