<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        // Already-signed-in users hitting a login page land on their own portal
        // instead of the framework default (the public landing page).
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();
            return $user ? route($user->homeRoute()) : route('home');
        });

        // Payment vendors POST server-to-server with no browser session, so there is
        // no CSRF token to present. These routes authenticate the caller by verifying
        // the vendor's HMAC signature instead — see PaymentGatewayController::webhook.
        $middleware->validateCsrfTokens(except: [
            'pay/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
