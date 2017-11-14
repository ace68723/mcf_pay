<?php

namespace App\Providers\RttService;

use Illuminate\Support\ServiceProvider;

class RttServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('rtt_service', function ($app) {
            return new RttService(); // You can even put some params here
        });
    }
}
