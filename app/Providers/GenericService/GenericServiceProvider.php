<?php

namespace App\Providers\GenericService;

use Illuminate\Support\ServiceProvider;

class GenericServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('generic_service', function ($app) {
            return new GenericService(); // You can even put some params here
        });
    }
}
