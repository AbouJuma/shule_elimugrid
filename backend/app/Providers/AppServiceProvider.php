<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(SmsGateway::class, function () {
            if (config('services.sms.driver') === 'beem') {
                return new BeemSmsGateway(
                    config('services.sms.beem.base_url'),
                    config('services.sms.beem.api_key'),
                    config('services.sms.beem.secret_key'),
                    config('services.sms.beem.sender_id'),
                );
            }

            return new LogSmsGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
