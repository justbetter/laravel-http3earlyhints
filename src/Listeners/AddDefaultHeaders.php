<?php

namespace JustBetter\Http3EarlyHints\Listeners;

use Illuminate\Support\Facades\Event;
use JustBetter\Http3EarlyHints\Events\GenerateEarlyHints;

class AddDefaultHeaders
{
    public function handle(GenerateEarlyHints $event)
    {
        foreach (config('http3earlyhints.default_headers', []) as $header) {
            $event->linkHeaders->addFromString($header);
        }
    }

    public static function register()
    {
        Event::listen(GenerateEarlyHints::class, static::class);
    }
}
