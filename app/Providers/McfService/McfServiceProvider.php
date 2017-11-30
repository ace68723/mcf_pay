<?php

namespace App\Providers\McfService;

use Illuminate\Support\ServiceProvider;

class McfServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('mcf_service', function ($app) {
            return new McfService(); // You can even put some params here
        });
    }
}
