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
        //
    }

    /**
     * Register the application services.
     *
     * @return Musqlidb
     */
    public function register()
    {
//         $this->app->bind('musqlidb', function() {
//             return new Musqlidb;
//         });
        $this->app->bind('musqlidb', 'PsgdevMusqlidb\Musqlidb');
    }
}
