<?php

namespace JustBetter\Http3EarlyHints;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'http3earlyhints');

        $this->publishes([
            __DIR__.'/config.php' => config_path('http3earlyhints.php'),
        ], 'config');
    }
}
