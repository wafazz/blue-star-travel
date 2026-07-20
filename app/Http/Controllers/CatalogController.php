<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\Provider;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function home()
    {
        $active = Package::with('pricings')->where('status', 'active')->get();

        $featured = $active->where('featured', true)->take(6);
        if ($featured->count() < 3) {
            $featured = $active->take(6);
        }

        $categories = [];
        foreach (Package::CATEGORIES as $key => $label) {
            $count = $active->where('category', $key)->count();
            if ($count > 0) {
                $categories[] = ['key' => $key, 'label' => $label, 'count' => $count];
            }
        }

        $destinations = $active->whereNotNull('destination')
            ->sortByDesc('featured')
            ->unique('destination')
            ->take(8)
            ->values();

        $departures = PackageDate::with('package')
            ->where('status', 'open')
            ->whereDate('depart_date', '>=', today())
            ->whereHas('package', fn ($q) => $q->where('status', 'active'))
            ->orderBy('depart_date')
            ->take(4)
            ->get()
            ->filter(fn ($d) => $d->seats_total - $d->seats_booked > 0);

        $stats = [
            'packages'     => $active->count(),
            'destinations' => $active->pluck('destination')->filter()->unique()->count(),
            'travellers'   => Customer::count() + Booking::count(),
            'providers'    => Provider::count(),
        ];

        $company = Company::current();

        return view('welcome', compact('featured', 'categories', 'destinations', 'departures', 'stats', 'company'));
    }

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
