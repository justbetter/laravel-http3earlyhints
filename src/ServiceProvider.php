<?php declare(strict_types=1);

namespace JustBetter\Http3EarlyHints;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use JustBetter\Http3EarlyHints\Listeners\AddDefaultHeaders;
use JustBetter\Http3EarlyHints\Listeners\AddFromBody;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'http3earlyhints');

        $this->publishes([
            __DIR__.'/config.php' => config_path('http3earlyhints.php'),
        ], 'config');

        AddDefaultHeaders::register();
        AddFromBody::register();
    }
}
