@extends('layouts.admin')
@section('title', 'Banners')
@section('console', 'Management')
@section('heading', 'Promotional Banners')

@section('content')
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">New Banner</h6>
        <form method="POST" action="{{ route('manage.banners.store') }}" enctype="multipart/form-data">
          @csrf
          <label class="form-label small fw-semibold">Title</label>
          <input type="text" name="title" class="form-control mb-2" required>
          <label class="form-label small fw-semibold">Subtitle</label>
          <input type="text" name="subtitle" class="form-control mb-2">
          <label class="form-label small fw-semibold">Image</label>
          <input type="file" name="image" accept="image/*" class="form-control mb-2">
          <label class="form-label small fw-semibold">Link URL</label>
          <input type="text" name="link_url" class="form-control mb-2" placeholder="https://…">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Placement</label><select name="placement" class="form-select"><option value="agent">Agent</option><option value="customer">Customer</option><option value="both">Both</option></select></div>
            <div class="col-6"><label class="form-label small fw-semibold">Sort</label><input type="number" name="sort" value="0" min="0" class="form-control"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Starts</label><input type="date" name="starts_at" class="form-control"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Ends</label><input type="date" name="ends_at" class="form-control"></div>
          </div>
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="active" value="1" checked><label class="form-check-label small">Active</label></div>
          <button class="btn btn-brand w-100">＋ Create Banner</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="row g-3">
        @forelse ($banners as $b)
          <div class="col-md-6">
            <div class="card h-100 overflow-hidden">
              <div style="height:120px;background:linear-gradient(135deg,#1466ff,#0b3fd1)">
                @if ($b->image)<img src="{{ asset('storage/' . $b->image) }}" class="w-100 h-100" style="object-fit:cover" alt="">@else<div class="d-flex align-items-center justify-content-center h-100 text-white fs-2">🖼️</div>@endif
              </div>
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div><div class="fw-bold">{{ $b->title }}</div><div class="small text-secondary">{{ $b->subtitle }}</div></div>
                  <span class="badge text-bg-{{ $b->active ? 'success' : 'secondary' }}">{{ $b->active ? 'Active' : 'Off' }}</span>
                </div>
                <div class="small text-secondary mt-2">📍 {{ ucfirst($b->placement) }} · sort {{ $b->sort }}</div>
                <form method="POST" action="{{ route('manage.banners.destroy', $b) }}" class="mt-2" onsubmit="return confirm('Delete banner?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger w-100">🗑 Delete</button></form>
              </div>
            </div>
          </div>
        @empty
          <div class="col-12"><div class="card p-5 text-center text-secondary">No banners yet.</div></div>
        @endforelse
      </div>
      <div class="mt-3">{{ $banners->links() }}</div>
    </div>
  </div>
@endsection
