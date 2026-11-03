<?php

namespace App\Providers;

use App\Services\WebPushService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // VAPID is only attached once keys exist, so the app still boots (and in-app
        // notifications still work) on a machine that has not configured web push yet.
        $this->app->bind(WebPush::class, fn () => new WebPush(WebPushService::configured() ? [
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ] : []));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
