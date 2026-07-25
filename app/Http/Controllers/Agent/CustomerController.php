<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::where('agent_id', $request->user()->id)
            ->withCount('bookings')
            ->orderBy('name')
            ->paginate(20);

        return view('agent.customers.index', compact('customers'));
    }

    public function create()
    {
        return view('agent.customers.form', ['customer' => new Customer()]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request) + [
            'agent_id' => $request->user()->id,
            'status'   => 'active',
        ]);

        return redirect()->route('agent.customers.index')->with('ok', 'Customer registered.');
    }

    public function edit(Request $request, Customer $customer)
    {
        abort_unless($customer->agent_id === $request->user()->id, 403);

        return view('agent.customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($customer->agent_id === $request->user()->id, 403);

        $customer->update($this->validated($request));

        return redirect()->route('agent.customers.index')->with('ok', 'Customer updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
