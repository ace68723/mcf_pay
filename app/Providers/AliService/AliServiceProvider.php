<?php

namespace App\Providers\AliService;

use Illuminate\Support\ServiceProvider;

class AliServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('ali_service', function ($app) {
            return new AliService(); // You can even put some params here
        });
    }
}
