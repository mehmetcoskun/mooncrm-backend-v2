<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Setting;
use App\Observers\CustomerObserver;
use App\Observers\SettingObserver;
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
        Customer::observe(CustomerObserver::class);
        Setting::observe(SettingObserver::class);
    }
}
