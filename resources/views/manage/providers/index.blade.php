@extends('layouts.admin')
@section('title', 'Providers')
@section('console', 'Management')
@section('heading', 'Providers')

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search providers…" style="min-width:200px">
      <select name="type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">All types</option>
        @foreach (\App\Models\Provider::TYPES as $k => $label)
          <option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <a href="{{ route('manage.providers.create') }}" class="btn btn-brand btn-sm">＋ New Provider</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Type</th><th>Contact</th><th>Packages</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          @forelse ($providers as $provider)
            <tr>
              <td class="fw-semibold">{{ $provider->name }}</td>
              <td><span class="badge text-bg-light">{{ $provider->typeLabel() }}</span></td>
              <td class="small">
                {{ $provider->contact_person ?: '—' }}
                @if ($provider->phone)<div class="text-secondary">{{ $provider->phone }}</div>@endif
              </td>
              <td>{{ $provider->packages_count }}</td>
              <td>
                <span class="badge text-bg-{{ $provider->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($provider->status) }}</span>
              </td>
              <td class="text-end">
                <a href="{{ route('manage.providers.edit', $provider) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                <form action="{{ route('manage.providers.destroy', $provider) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this provider?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-secondary py-4">No providers yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $providers->links() }}</div>
@endsection
