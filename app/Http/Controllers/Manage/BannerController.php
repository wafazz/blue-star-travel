<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort')->paginate(15);

        return view('manage.banners.index', compact('banners'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        Banner::create($data);

        return back()->with('ok', 'Banner created.');
    }

    public function update(Banner $banner, Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        $banner->update($data);

        return back()->with('ok', 'Banner updated.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();

        return back()->with('ok', 'Banner deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:120'],
            'subtitle'  => ['nullable', 'string', 'max:255'],
            'image'     => ['nullable', 'image', 'max:4096'],
            'link_url'  => ['nullable', 'string', 'max:255'],
            'placement' => ['required', 'in:agent,customer,both'],
            'sort'      => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date'],
            'active'    => ['nullable', 'boolean'],
        ]);
        $data['sort'] = $data['sort'] ?? 0;
        $data['active'] = $request->boolean('active');
        unset($data['image']); // handled by caller

        return $data;
    }
}
