<?php

namespace JustBetter\Http3EarlyHints\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AddHttp3EarlyHints
{
    protected ?Crawler $crawler;

    protected bool $skipCurrentRequest = false;

    protected ?int $limit = null;

    protected ?int $sizeLimit = null;

    protected ?array $excludeKeywords = null;

    public function handle(Request $request, Closure $next, ?int $limit = null, ?int $sizeLimit = null, ?array $excludeKeywords = null): mixed
    {
        $lastPath = Str::afterLast($request->path(), '/');
        if (
            $request->isJson()
            || (str_contains($lastPath, '.') && ! in_array(Str::afterLast($lastPath, '.'), $this->getConfig('extensions', ['', 'php', 'html'])))
        ) {
            $this->skipCurrentRequest = true;

            return $next($request);
        }

        $this->limit = $limit;
        $this->sizeLimit = $sizeLimit;
        $this->excludeKeywords = $excludeKeywords;

        $linkHeaders = Cache::store($this->getConfig('cache_driver'))->get('earlyhints-'.md5($request->url()));
        if (! $linkHeaders) {
            $response = $next($request);
            $linkHeaders = $this->handleGeneratingLinkHeaders($request, $response);
            if ($linkHeaders) {
                $this->addLinkHeader($response, $linkHeaders);
            }

            return $response;
        }

        if ($this->getConfig('set_103')) {
            $response = new Response();
            $this->addLinkHeader($response, $linkHeaders);
            $response->sendHeaders(103);

            $realResponse = $next($request);
            $this->addLinkHeader($realResponse, $linkHeaders);

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
        $this->addLinkHeader($response, $linkHeaders);

        return $response;
    }

    /**
     * We only start crawling once the response has already been sent to the client in order to reduce impact on performance.
     */
    public function terminate(Request $request, \Symfony\Component\HttpFoundation\Response $response): void
    {
        $this->handleGeneratingLinkHeaders($request, $response);
    }

    public function handleGeneratingLinkHeaders(Request $request, \Symfony\Component\HttpFoundation\Response $response)
    {
        if (
            ! $response instanceof Response
            || $response->isRedirection()
            || ! $response->isSuccessful()
            || $this->skipCurrentRequest
        ) {
            return;
        }
        $linkHeaders = $this->generateLinkHeaders($response, $this->limit, $this->sizeLimit, $this->excludeKeywords);

        Cache::store($this->getConfig('cache_driver'))->put(
            'earlyhints-'.md5($request->url()),
            $linkHeaders,
            $this->getConfig('cache_duration', 864000)
        );

        return $linkHeaders;
    }

    public function getConfig(mixed $key, mixed $default = false): mixed
    {
        if (! function_exists('config')) { // for tests..
            return $default;
        }

        return config('http3earlyhints.'.$key, $default);
    }

    protected function generateLinkHeaders(Response $response, ?int $limit = null, ?int $sizeLimit = null, ?array $excludeKeywords = null): Collection
    {
        $excludeKeywords = array_filter($excludeKeywords ?? $this->getConfig('exclude_keywords', []));
        $headers = $this->fetchLinkableNodes($response)
            ->flatMap(function ($element) {
                [$src, $href, $data, $rel, $type] = $element;
                $rel = $type === 'module' ? 'modulepreload' : $rel;

                return [
                    $this->buildLinkHeaderString($src ?? '', $rel ?? null),
                    $this->buildLinkHeaderString($href ?? '', $rel ?? null),
                    $this->buildLinkHeaderString($data ?? '', $rel ?? null),
                ];
            })
            ->merge($this->getConfig('default_headers', []))
            ->unique()
            ->filter(function ($value, $key) use ($excludeKeywords) {
                if (! $value) {
                    return false;
                }
                $exclude_keywords = collect($excludeKeywords)->map(function ($keyword) {
                    return preg_quote($keyword);
                });
                if ($exclude_keywords->count() <= 0) {
                    return true;
                }

                return ! preg_match('%('.$exclude_keywords->implode('|').')%i', $value);
            })
            ->take($limit);

        $sizeLimit = $sizeLimit ?? max(1, intval($this->getConfig('size_limit', 32 * 1024)));
        $headersText = trim($headers->implode(','));
        while (strlen($headersText) > $sizeLimit) {
            $headers->pop();
            $headersText = trim($headers->implode(','));
        }

        return $headers;
    }

    /**
     * Get the DomCrawler instance.
     */
    protected function getCrawler(Response $response): Crawler
    {
        return $this->crawler ??= new Crawler($response->getContent());
    }

    /**
     * Get all nodes we are interested in pushing.
     */
    protected function fetchLinkableNodes(Response $response): Collection
    {
        $crawler = $this->getCrawler($response);

        return collect($crawler->filter('link:not([rel*="icon"]):not([rel="canonical"]):not([rel="manifest"]):not([rel="alternate"]), script[src], *:not(picture)>img[src]:not([loading="lazy"]), object[data]')->extract(['src', 'href', 'data', 'rel', 'type']));
    }

    /**
     * Build out header string based on asset extension.
     */
    private function buildLinkHeaderString(string $url, ?string $rel = 'preload'): ?string
    {
        $linkTypeMap = [
            '.CSS' => 'style',
            '.JS' => 'script',
            '.BMP' => 'image',
            '.GIF' => 'image',
            '.JPG' => 'image',
            '.JPEG' => 'image',
            '.PNG' => 'image',
            '.SVG' => 'image',
            '.TIFF' => 'image',
            '.WEBP' => 'image',
            '.WOFF' => 'font',
            '.WOFF2' => 'font',
        ];

        $type = collect($linkTypeMap)->first(function ($type, $extension) use ($url) {
            return Str::contains(strtoupper($url), $extension);
        });

        if (! preg_match('%^(https?:)?//%i', $url)) {
            $basePath = $this->getConfig('base_path', '/');
            $url = rtrim($basePath.ltrim($url, $basePath), '/');
        }

        if ($rel === 'preconnect' && $url) {
            return "<{$url}>; rel={$rel}";
        }

        if (! in_array($rel, ['preload', 'modulepreload'])) {
            $rel = 'preload';
        }

        if ($url && ! $type) {
            $type = 'fetch';
        }

        return is_null($type) ? null : "<{$url}>; rel={$rel}; as={$type}".($type == 'font' ? '; crossorigin' : '');
    }

    /**
     * Add Link Header
     */
    private function addLinkHeader(\Symfony\Component\HttpFoundation\Response $response, mixed $link): Response
    {
        $link = trim(collect($link)->implode(','));
        if (! $link || !$response instanceof Response) {
            return $response;
        }
        if ($response->headers->get('Link')) {
            $link = $response->headers->get('Link').','.$link;
        }

        $response->header('Link', $link);

        return $response;
    }
}
