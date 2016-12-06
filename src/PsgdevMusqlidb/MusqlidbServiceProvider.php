<?php

namespace PsgdevMusqlidb;

use Illuminate\Support\ServiceProvider;

class MusqlidbServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
         echo 'pera service';
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['pera'] = $this->app->share(function($app) {
            return new Pera();
        });
    }
}
