<?php

namespace App\Providers;

use App\Notifications\Channels\BrevoChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('brevo', fn () => new BrevoChannel);

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceRootUrl(rtrim($appUrl, '/'));
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
    }
}
