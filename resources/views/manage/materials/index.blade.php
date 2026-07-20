@extends('layouts.admin')
@section('title', 'Marketing Materials')
@section('console', 'Management')
@section('heading', 'Marketing Materials')

@section('content')
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Upload Material</h6>
        <form method="POST" action="{{ route('manage.materials.store') }}" enctype="multipart/form-data">
          @csrf
          <label class="form-label small fw-semibold">Title</label>
          <input type="text" name="title" class="form-control mb-2" required>
          <label class="form-label small fw-semibold">Description</label>
          <input type="text" name="description" class="form-control mb-2">
          <label class="form-label small fw-semibold">Category</label>
          <select name="category" class="form-select mb-2">
            @foreach (\App\Models\MarketingMaterial::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
          </select>
          <label class="form-label small fw-semibold">File</label>
          <input type="file" name="file" class="form-control mb-2">
          <label class="form-label small fw-semibold">Or external URL</label>
          <input type="text" name="external_url" class="form-control mb-2" placeholder="https://…">
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="active" value="1" checked><label class="form-check-label small">Active</label></div>
          <button class="btn btn-brand w-100">＋ Add Material</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Material</th><th>Category</th><th>Downloads</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse ($materials as $m)
                <tr>
                  <td><span class="me-1">{{ $m->icon() }}</span><span class="fw-semibold">{{ $m->title }}</span><div class="text-secondary" style="font-size:.72rem">{{ $m->description }}</div></td>
                  <td class="small">{{ $m->categoryLabel() }}</td>
                  <td class="small">{{ $m->downloads_count }}</td>
                  <td><span class="badge text-bg-{{ $m->active ? 'success' : 'secondary' }}">{{ $m->active ? 'Active' : 'Off' }}</span></td>
                  <td class="text-end text-nowrap">
                    @if ($m->file_path)<a href="{{ asset('storage/' . $m->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>@endif
                    <form method="POST" action="{{ route('manage.materials.destroy', $m) }}" class="d-inline" onsubmit="return confirm('Delete material?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑</button></form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-secondary py-5">No materials yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-3">{{ $materials->links() }}</div>
    </div>
  </div>
@endsection
