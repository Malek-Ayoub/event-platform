<?php

namespace App\Providers;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\Domain\Correlation\CorrelationContext;
use App\Services\TransactionRunner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // Suite-wide array cache would otherwise share email|ip buckets across Feature tests.
            $maxAttempts = $this->app->runningUnitTests() ? 1_000 : 5;

            return Limit::perMinute($maxAttempts)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('api', function (Request $request) {
            // Keep Feature suites from exhausting a shared IP bucket under the baseline limiter.
            $maxAttempts = $this->app->runningUnitTests() ? 10_000 : 120;

            return Limit::perMinute($maxAttempts)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
