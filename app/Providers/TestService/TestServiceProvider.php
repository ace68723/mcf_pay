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

        $this->app->singleton('test_service', function ($app) {
            return new TestService(); // You can even put some params here
        });
    }
}
