<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = Provider::query()->withCount('packages');

        if ($search = trim((string) $request->get('q'))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $providers = $query->latest()->paginate(12)->withQueryString();

        return view('manage.providers.index', compact('providers'));
    }

    public function create()
    {
        return view('manage.providers.form', ['provider' => new Provider()]);
    }

    public function store(Request $request)
    {
        Provider::create($this->validated($request));

        return redirect()->route('manage.providers.index')->with('ok', 'Provider created.');
    }

    public function edit(Provider $provider)
    {
        return view('manage.providers.form', compact('provider'));
    }

    public function update(Request $request, Provider $provider)
    {
        $provider->update($this->validated($request));

        return redirect()->route('manage.providers.index')->with('ok', 'Provider updated.');
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();

        return redirect()->route('manage.providers.index')->with('ok', 'Provider deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:' . implode(',', array_keys(Provider::TYPES))],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'country'        => ['nullable', 'string', 'max:255'],
            'status'         => ['required', 'in:active,inactive'],
            'notes'          => ['nullable', 'string'],
        ]);
    }
}
