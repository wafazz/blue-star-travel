<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    // portal => [allowed roles, login view, portal label]
    const PORTALS = [
        'staff'    => [['super_admin', 'hq', 'admin'], 'auth.staff-login', 'Staff'],
        'agent'    => [['agent'], 'auth.agent-login', 'Agent'],
        'customer' => [['customer'], 'auth.customer-login', 'Customer'],
        'provider' => [['provider'], 'auth.provider-login', 'Provider'],
    ];

    public function show(string $portal = 'customer')
    {
        $config = self::PORTALS[$portal] ?? self::PORTALS['customer'];

        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        return view($config[1], ['portal' => $portal, 'portalLabel' => $config[2]]);
    }

    public function login(Request $request, string $portal = 'customer')
    {
        $config = self::PORTALS[$portal] ?? self::PORTALS['customer'];
        $allowedRoles = $config[0];

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->hasRole(...$allowedRoles)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account cannot sign in from the ' . $config[2] . ' portal.',
            ]);
        }

        if ($user->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Your account is ' . $user->status . '. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request, string $portal = 'customer')
    {
        $loginRoute = $portal === 'staff' ? 'admin.login'
            : ($portal === 'agent' ? 'agent.login'
            : ($portal === 'provider' ? 'provider.login' : 'login'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute);
    }
}
