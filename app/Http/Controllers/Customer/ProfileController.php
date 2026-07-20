<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $customer = $request->user()->customerProfile;
        abort_unless($customer, 403, 'No customer profile linked to this account.');

        return view('customer.profile', compact('customer'));
    }

    public function update(Request $request)
    {
        $user     = $request->user();
        $customer = $user->customerProfile;
        abort_unless($customer, 403, 'No customer profile linked to this account.');

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:120'],
            'phone'                   => ['required', 'string', 'max:30'],
            'ic_passport_no'          => ['nullable', 'string', 'max:50'],
            'passport_expiry'         => ['nullable', 'date'],
            'nationality'             => ['nullable', 'string', 'max:60'],
            'dob'                     => ['nullable', 'date'],
            'gender'                  => ['nullable', 'in:male,female'],
            'address'                 => ['nullable', 'string', 'max:255'],
            'city'                    => ['nullable', 'string', 'max:80'],
            'state'                   => ['nullable', 'string', 'max:80'],
            'postcode'                => ['nullable', 'string', 'max:12'],
            'country'                 => ['nullable', 'string', 'max:80'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer->update($data);
        $user->update(['name' => $data['name'], 'phone' => $data['phone']]);

        return back()->with('ok', 'Profile updated.');
    }
}
