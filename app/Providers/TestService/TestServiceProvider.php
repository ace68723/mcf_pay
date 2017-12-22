<?php

namespace App\Providers\TestService;

use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('tc_vendor_service', function ($app) {
            return new TestChannelService(); // You can even put some params here
        });
    }
}
