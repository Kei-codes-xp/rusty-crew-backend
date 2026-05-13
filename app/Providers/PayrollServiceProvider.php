<?php

namespace App\Providers;

use App\Services\PayrollService;
use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayrollService::class, function () {
            return new PayrollService();
        });
    }
 
    public function boot(): void
    {
        // Nothing to boot
    }
}
