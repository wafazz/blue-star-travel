<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * /account/profile for every signed-in account. Customers (who have a customer profile) get the
 * passport/address form; agents and back-office staff get name, phone and title.
 * Email is the login and is changed by HQ only, so every variant rejects a submitted email.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        if ($customer = $user->customerProfile) {
            return view('customer.profile', compact('customer'));
        }

        return view($this->accountView($user), ['user' => $user]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->customerProfile) {
            return $this->updateCustomer($request, $user);
        }

        $this->accountView($user); // aborts for accounts without a profile page

        $data = $request->validate([
            'email' => ['prohibited'],
            'name'  => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'title'   => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name'    => trim($data['name']),
            'phone'   => trim((string) ($data['phone'] ?? '')) ?: null,
            'title'   => trim((string) ($data['title'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
        ]);

        return back()->with('ok', 'Profile updated.');
    }

    private function accountView(User $user): string
    {
        abort_unless($user->role === 'agent' || $user->isStaff(), 403, 'No profile page for this account.');

        return $user->role === 'agent' ? 'account.profile-agent' : 'account.profile-staff';
    }

    private function updateCustomer(Request $request, User $user)
    {
        $customer = $user->customerProfile;

        $data = $request->validate([
            'email'                   => ['prohibited'],
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
