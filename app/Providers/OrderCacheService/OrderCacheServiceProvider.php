<?php

namespace App\Providers\OrderCacheService;

use Illuminate\Support\ServiceProvider;

class OrderCacheServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('order_cache_service', function ($app) {
            return new OrderCacheService(); // You can even put some params here
        });
    }
}
