<?php

namespace JustBetter\Http3EarlyHints\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JustBetter\Http3EarlyHints\Data\LinkHeaders;
use JustBetter\Http3EarlyHints\Events\GenerateEarlyHints;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AddHttp3EarlyHints
{
    protected ?LinkHeaders $linkHeaders;

    protected bool $skipCurrentRequest = false;

    protected ?int $sizeLimit = null;

    public function handle(Request $request, Closure $next, ?int $sizeLimit = null): mixed
    {
        $lastPath = Str::afterLast($request->path(), '/');
        if (
            $request->format() !== 'html'
            || (str_contains($lastPath, '.') && ! in_array(Str::afterLast($lastPath, '.'), config('http3earlyhints.extensions', ['', 'php', 'html'])))
        ) {
            $this->skipCurrentRequest = true;

            return $next($request);
        }

        $this->sizeLimit = $sizeLimit;

        $linkHeaders = Cache::store(config('http3earlyhints.cache_driver'))->get('earlyhints-'.md5($request->url()));
        if (! $linkHeaders) {
            $response = $next($request);
            if (! config('http3earlyhints.generate_during_request', true)) {
                return $response;
            }
            $linkHeaders = $this->handleGeneratingLinkHeaders($request, $response);
            if ($linkHeaders) {
                $this->addLinkHeaders($response, $linkHeaders);
            }

            return $response;
        }

        if (config('http3earlyhints.send_103')) {
            $response = new Response;
            $response->headers->remove('cache-control');
            $this->addLinkHeaders($response, $linkHeaders);
            $response->sendHeaders(103);

            $realResponse = $next($request);
            $this->addLinkHeaders($realResponse, $linkHeaders);

            $reflectionResponse = new ReflectionClass(SymfonyResponse::class);
            $reflectionSentHeaders = $reflectionResponse->getProperty('sentHeaders');
            if ($reflectionSentHeaders->isInitialized($response)) {
                $reflectionSentHeaders->setValue(
                    $realResponse,
                    $reflectionSentHeaders->getValue($response)
                );
            }

            return $realResponse;
        }

        $response = $next($request);
        $this->addLinkHeaders($response, $linkHeaders);

        return $response;
    }

    /**
     * We only start crawling once the response has already been sent to the client in order to reduce impact on performance.
     */
    public function terminate(Request $request, SymfonyResponse $response): void
    {
        $this->handleGeneratingLinkHeaders($request, $response);
    }

    public function handleGeneratingLinkHeaders(Request $request, SymfonyResponse $response)
    {
        if (
            ! $response instanceof Response
            || $response->isRedirection()
            || ! $response->isSuccessful()
            || $this->skipCurrentRequest
        ) {
            return;
        }
        $linkHeaders = $this->generateLinkHeaders($request, $response, $this->sizeLimit);

        Cache::store(config('http3earlyhints.cache_driver'))->put(
            'earlyhints-'.md5($request->url()),
            $linkHeaders,
            config('http3earlyhints.cache_duration', 864000)
        );

        return $linkHeaders;
    }

    protected function generateLinkHeaders(Request $request, SymfonyResponse $response, ?int $sizeLimit = null): LinkHeaders
    {
        $this->linkHeaders = new LinkHeaders;
        GenerateEarlyHints::dispatch($this->linkHeaders, $request, $response);

        $this->linkHeaders->makeUnique();

        $sizeLimit = $sizeLimit ?? max(1, intval(config('http3earlyhints.size_limit', 32 * 1024)));
        $headersText = $this->linkHeaders->__toString();

        while (strlen($headersText) > $sizeLimit) {
            $this->linkHeaders->setLinkProvider($this->linkHeaders->getLinkProvider()->withOutLink(Arr::last($this->linkHeaders->getLinkProvider()->getLinks())));
            $headersText = $this->linkHeaders->__toString();
        }

        return $this->linkHeaders;
    }

    /**
     * Add Link Header
     */
    private function addLinkHeaders(SymfonyResponse $response, LinkHeaders $linkHeaders): SymfonyResponse
    {
        $link = $linkHeaders->__toString();
        if (! $link || ! $response instanceof Response) {
            return $response;
        }
        if ($response->headers->get('Link')) {
            $link = $response->headers->get('Link').','.$link;
        }

        $response->header('Link', $link);

        return $response;
    }
}
