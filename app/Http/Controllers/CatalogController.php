<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::with('pricings', 'provider')->where('status', 'active');

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }
        if ($search = $request->get('q')) {
            $query->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")->orWhere('destination', 'like', "%{$search}%");
            });
        }

        $packages = $query->orderByDesc('featured')->orderBy('title')->paginate(12)->withQueryString();

        return view('catalog.index', compact('packages'));
    }

    public function show(string $slug)
    {
        $package = Package::with('pricings', 'provider', 'dates')
            ->where('slug', $slug)->where('status', 'active')->firstOrFail();

        $dates = $package->dates
            ->where('status', 'open')
            ->filter(fn ($d) => $d->seats_total - $d->seats_booked > 0)
            ->sortBy('depart_date');

        return view('catalog.show', compact('package', 'dates'));
    }
}
