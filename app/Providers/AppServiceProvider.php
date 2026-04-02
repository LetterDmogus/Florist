<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('lookup-search', fn (Request $request): Limit => Limit::perMinute(90)->by($this->limiterKey($request)));

        RateLimiter::for('sensitive-write', fn (Request $request): Limit => Limit::perMinute(45)->by($this->limiterKey($request)));

        RateLimiter::for('order-status-update', fn (Request $request): Limit => Limit::perMinute(30)->by($this->limiterKey($request)));

        RateLimiter::for('report-export', fn (Request $request): Limit => Limit::perMinute(10)->by($this->limiterKey($request)));
    }

    private function limiterKey(Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';

        return "{$userId}|{$request->ip()}";
    }
}
