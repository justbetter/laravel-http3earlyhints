<?php

declare(strict_types=1);

namespace JustBetter\Http3EarlyHints\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use JustBetter\Http3EarlyHints\Data\LinkHeaders;
use Symfony\Component\HttpFoundation\Response;

class GenerateEarlyHints
{
    use Dispatchable;

    public function __construct(
        public LinkHeaders $linkHeaders,
        public Request $request,
        public Response $response
    ) {}
}
