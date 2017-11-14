<?php

namespace App\Providers\WxService;

use Illuminate\Support\ServiceProvider;

class WxServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('wx_service', function ($app) {
            return new WxService(); // You can even put some params here
        });
    }
}
