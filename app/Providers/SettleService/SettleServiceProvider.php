<?php

namespace App\Providers\SettleService;

use Illuminate\Support\ServiceProvider;

class SettleServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('settle_service', function ($app) {
            return new SettleService(); // You can even put some params here
        });
    }
}
