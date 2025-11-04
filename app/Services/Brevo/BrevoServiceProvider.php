<?php

namespace App\Services\Brevo;

use Illuminate\Support\ServiceProvider;

class BrevoServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->app->singleton(BrevoClient::class, function ($app) {
            return new BrevoClient(config('services.brevo.api_key'));
        });

        $this->app->alias(BrevoClient::class, 'brevo');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        //
    }
}
