<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Services\Payments\AzamPayGateway;
use App\Services\Payments\LogPaymentGateway;
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

        $this->app->bind(PaymentGateway::class, function () {
            if (config('services.payment_gateway.driver') === 'azampay') {
                return new AzamPayGateway(
                    config('services.payment_gateway.azampay.auth_base_url'),
                    config('services.payment_gateway.azampay.checkout_base_url'),
                    config('services.payment_gateway.azampay.app_name'),
                    config('services.payment_gateway.azampay.client_id'),
                    config('services.payment_gateway.azampay.client_secret'),
                );
            }

            return new LogPaymentGateway;
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
