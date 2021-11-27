<?php

namespace PetrPetrovich\POS;

use Illuminate\Support\ServiceProvider;

class POSServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([__DIR__.'/../config/pos.php' => config_path('pos.php')]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
