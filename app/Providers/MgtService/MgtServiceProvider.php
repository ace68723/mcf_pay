<?php

namespace App\Providers\MgtService;

use Illuminate\Support\ServiceProvider;

class MgtServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('mgt_service', function ($app) {
            return new MgtService(); // You can even put some params here
        });
    }
}
