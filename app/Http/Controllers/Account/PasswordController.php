<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Change password for every signed-in account (customer, agent, admin, HQ, super admin). */
class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return view('account.password-staff');
        }

        return view('account.password-agent', [
            'layout' => $user->role === 'customer' ? 'layouts.customer' : 'layouts.agent',
            'back'   => route('customer.profile.edit'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'min:8', 'different:current_password'],
        ], [
            'password.different' => 'The new password must be different from your current one.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('ok', 'Password changed.');
    }
}
