<?php

namespace App\Providers;

use App\Services\DartCollector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DartCollector::class, function () {
            return new DartCollector('4a947b2d03c602ddf6c6ed465f69cb3276ad6c29');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
