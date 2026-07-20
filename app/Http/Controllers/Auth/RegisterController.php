<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.customer-register', ['ref' => $request->get('ref')]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'ref'      => ['nullable', 'string', 'max:20'],
        ]);

        // A referral code links the new customer to the agent who brought them in.
        $agentId = null;
        if (! empty($data['ref'])) {
            $agentId = User::where('role', 'agent')->where('agent_code', $data['ref'])->value('id');
        }

        $user = DB::transaction(function () use ($data, $agentId) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'],
                'role'     => 'customer',
                'status'   => 'active',
                'password' => $data['password'],
            ]);

            Customer::create([
                'user_id'  => $user->id,
                'agent_id' => $agentId,
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'],
                'status'   => 'active',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')->with('ok', 'Welcome to Blue Travel! 🎉');
    }
}
