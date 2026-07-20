<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->with('agent');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(15)->withQueryString();

        return view('manage.customers.index', compact('customers'));
    }

    public function create()
    {
        return view('manage.customers.form', [
            'customer' => new Customer(),
            'agents'   => $this->agents(),
        ]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return redirect()->route('manage.customers.index')->with('ok', 'Customer created.');
    }

    public function edit(Customer $customer)
    {
        return view('manage.customers.form', [
            'customer' => $customer,
            'agents'   => $this->agents(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('manage.customers.index')->with('ok', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('manage.customers.index')->with('ok', 'Customer deleted.');
    }

    private function agents()
    {
        return User::where('role', 'agent')->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'agent_id'        => ['nullable', 'exists:users,id'],
            'ic_passport_no'  => ['nullable', 'string', 'max:100'],
            'passport_expiry' => ['nullable', 'date'],
            'nationality'     => ['nullable', 'string', 'max:100'],
            'dob'             => ['nullable', 'date'],
            'gender'          => ['nullable', 'in:male,female,other'],
            'address'         => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:255'],
            'state'           => ['nullable', 'string', 'max:255'],
            'postcode'        => ['nullable', 'string', 'max:20'],
            'country'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'loyalty_points'  => ['nullable', 'integer', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'status'          => ['required', 'in:active,inactive'],
        ]);
    }
}
