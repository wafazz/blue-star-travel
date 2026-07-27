<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::query()->with('provider')->withCount('dates');

        if ($search = trim((string) $request->get('q'))) {
            $query->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
        }
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $packages = $query->latest()->paginate(10)->withQueryString();

        return view('manage.packages.index', compact('packages'));
    }

    public function create()
    {
        return view('manage.packages.form', [
            'package'   => new Package(['status' => 'draft', 'duration_days' => 1, 'duration_nights' => 0]),
            'providers' => Provider::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertDepartures($request, $data);
        $data['code'] = $this->nextCode();
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['featured'] = $request->boolean('featured');

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('packages', 'public');
        }
        $data['gallery'] = $this->storeGallery($request, []);

        $package = Package::create($data);
        $this->syncPricings($request, $package);
        $this->syncDates($request, $package);
        $this->syncCommissions($request, $package);

        return redirect()->route('manage.packages.edit', $package)->with('ok', 'Package created.');
    }

    public function show(Package $package)
    {
        $package->load('provider', 'pricings', 'dates');

        return view('manage.packages.show', compact('package'));
    }

    public function edit(Package $package)
    {
        $package->load('pricings', 'dates', 'commissionLevels');

        return view('manage.packages.form', [
            'package'   => $package,
            'providers' => Provider::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $data = $this->validated($request);
        $this->assertDepartures($request, $data);
        $data['slug'] = $this->uniqueSlug($data['title'], $package->id);
        $data['featured'] = $request->boolean('featured');

        if ($request->hasFile('cover_image')) {
            if ($package->cover_image) {
                Storage::disk('public')->delete($package->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('packages', 'public');
        }
        $data['gallery'] = $this->storeGallery($request, $package->gallery ?? []);

        $package->update($data);
        $this->syncPricings($request, $package);
        $this->syncDates($request, $package);
        $this->syncCommissions($request, $package);

        return redirect()->route('manage.packages.edit', $package)->with('ok', 'Package updated.');
    }

    public function destroy(Package $package)
    {
        if ($package->cover_image) {
            Storage::disk('public')->delete($package->cover_image);
        }
        foreach ($package->gallery ?? [] as $img) {
            Storage::disk('public')->delete($img);
        }
        $package->delete();

        return redirect()->route('manage.packages.index')->with('ok', 'Package deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'category'        => ['required', 'in:' . implode(',', array_keys(Package::CATEGORIES))],
            'provider_id'     => ['nullable', 'exists:providers,id'],
            'destination'     => ['nullable', 'string', 'max:255'],
            'duration_days'   => ['required', 'integer', 'min:1', 'max:365'],
            'duration_nights' => ['required', 'integer', 'min:0', 'max:365'],
            'date_mode'       => ['required', 'in:' . implode(',', array_keys(Package::DATE_MODES))],
            'cancellation_fee_per_pack' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'summary'         => ['nullable', 'string', 'max:500'],
            'description'     => ['nullable', 'string'],
            'itinerary'       => ['nullable', 'string'],
            'inclusions'      => ['nullable', 'string'],
            'exclusions'      => ['nullable', 'string'],
            'terms'           => ['nullable', 'string'],
            'status'          => ['required', 'in:draft,active,inactive'],
            'cover_image'     => ['nullable', 'image', 'max:3072'],
            'gallery.*'       => ['nullable', 'image', 'max:3072'],
        ]);
    }

    private function storeGallery(Request $request, array $existing): array
    {
        $gallery = $existing;
        foreach ((array) $request->file('gallery', []) as $file) {
            if ($file) {
                $gallery[] = $file->store('packages/gallery', 'public');
            }
        }
        return $gallery;
    }

    private function syncPricings(Request $request, Package $package): void
    {
        $rows = $request->input('pricings', []);
        $package->pricings()->delete();

        $default = $request->input('default_pricing', 0);
        foreach ($rows as $i => $row) {
            if (empty($row['tier_name'])) {
                continue;
            }
            $package->pricings()->create([
                'tier_name'    => $row['tier_name'],
                'capacity'     => max(1, (int) ($row['capacity'] ?? 2)),
                'adult_price'  => $row['adult_price'] ?? 0,
                'child_price'  => $row['child_price'] ?? 0,
                'senior_price' => $row['senior_price'] ?? 0,
                'infant_price' => $row['infant_price'] ?? 0,
                'promo_price'  => $row['promo_price'] ?: null,
                'early_bird_price' => $row['early_bird_price'] ?: null,
                'early_bird_until' => $row['early_bird_until'] ?: null,
                'group_min'    => $row['group_min'] ?: null,
                'group_discount_percent' => $row['group_discount_percent'] ?: null,
                'is_default'   => (string) $i === (string) $default,
            ]);
        }
    }

    private function syncCommissions(Request $request, Package $package): void
    {
        $rows = $request->input('commissions', []);
        $package->commissionLevels()->delete();

        $hqDone = false;
        foreach ($rows as $row) {
            $isHq  = ! empty($row['is_hq']);
            $level = $isHq ? 0 : (int) ($row['level'] ?? 0);
            if ($isHq) {
                if ($hqDone) {
                    continue; // only one HQ override per package
                }
                $hqDone = true;
            } elseif ($level < 1) {
                continue;
            }
            $package->commissionLevels()->create([
                'level'        => $level,
                'is_hq'        => $isHq,
                'rate_type'    => ($row['rate_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent',
                'adult_value'  => $row['adult_value'] ?? 0,
                'child_value'  => $row['child_value'] ?? 0,
                'senior_value' => $row['senior_value'] ?? 0,
                'infant_value' => $row['infant_value'] ?? 0,
                'active'       => ! isset($row['active']) || $row['active'],
            ]);
        }
    }

    /**
     * A live "scheduled departures only" package with no departures cannot be booked at all.
     * Drafts are left alone so a package can be built up over several saves.
     */
    private function assertDepartures(Request $request, array $data): void
    {
        if ($data['date_mode'] !== 'fixed' || $data['status'] !== 'active') {
            return;
        }

        $rows = array_filter($request->input('dates', []), fn ($r) => ! empty($r['depart_date']));
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'dates' => 'A scheduled-departures package must have at least one departure date before it can be active.',
            ]);
        }
    }

    private function syncDates(Request $request, Package $package): void
    {
        $rows = $request->input('dates', []);
        $package->dates()->delete();

        // An open-dated package has no departures to publish.
        if ($package->date_mode === 'open') {
            return;
        }

        foreach ($rows as $row) {
            if (empty($row['depart_date'])) {
                continue;
            }
            $package->dates()->create([
                'depart_date' => $row['depart_date'],
                'return_date' => $row['return_date'] ?: null,
                'seats_total' => $row['seats_total'] ?? 0,
                'seats_booked' => $row['seats_booked'] ?? 0,
                'status'      => $row['status'] ?? 'open',
            ]);
        }
    }

    private function nextCode(): string
    {
        $last = Package::orderByDesc('id')->value('id') ?? 0;
        return 'PKG-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $n = 1;
        while (Package::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }
}
