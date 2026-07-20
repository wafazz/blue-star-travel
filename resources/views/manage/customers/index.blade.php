@extends('layouts.admin')
@section('title', 'Customers')
@section('console', 'Management')
@section('heading', 'Customers')

@section('content')
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, email, phone…" style="min-width:240px">
      <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
    <a href="{{ route('manage.customers.create') }}" class="btn btn-brand btn-sm">＋ New Customer</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr><th>Customer</th><th>Contact</th><th>Passport / IC</th><th>Agent</th><th>Points</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          @forelse ($customers as $customer)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px">{{ $customer->initials() }}</span>
                  <span class="fw-semibold">{{ $customer->name }}</span>
                </div>
              </td>
              <td class="small">{{ $customer->email ?: '—' }}<div class="text-secondary">{{ $customer->phone }}</div></td>
              <td class="small">{{ $customer->ic_passport_no ?: '—' }}</td>
              <td class="small">{{ $customer->agent->name ?? '—' }}</td>
              <td><span class="badge text-bg-warning bg-opacity-25 text-dark">{{ number_format($customer->loyalty_points) }}</span></td>
              <td><span class="badge text-bg-{{ $customer->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($customer->status) }}</span></td>
              <td class="text-end">
                <a href="{{ route('manage.customers.edit', $customer) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                <form action="{{ route('manage.customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this customer?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-secondary py-4">No customers yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $customers->links() }}</div>
@endsection
