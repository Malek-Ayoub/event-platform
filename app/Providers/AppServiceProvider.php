<?php

namespace App\Providers;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\Domain\Correlation\CorrelationContext;
use App\Services\TransactionRunner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TransactionRunner::class);
        $this->app->scoped(CorrelationContextInterface::class, CorrelationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
