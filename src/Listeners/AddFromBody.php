<?php

namespace JustBetter\Http3EarlyHints\Listeners;

use Fig\Link\Link;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JustBetter\Http3EarlyHints\Events\GenerateEarlyHints;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

class AddFromBody
{
    protected ?Crawler $crawler;

    public function handle(GenerateEarlyHints $event)
    {
        $excludeKeywords = array_filter(config('http3earlyhints.exclude_keywords', []));
        $headers = $this->fetchLinkableNodes($event->response)
            ->flatMap(function ($element) {
                [$src, $href, $data, $rel, $type, $crossorigin, $as, $fetchpriority, $integrity, $referrerpolicy, $imagesizes, $imagesrcset] = $element;
                $rel = $type === 'module' ? 'modulepreload' : $rel;

                if ($rel === 'modulepreload' && empty($crossorigin)) {
                    // On module or modulepreload the crossorigin is REQUIRED https://github.com/whatwg/html/issues/1888
                    $crossorigin = 'anonymous';
                }

                $attributes = array_filter(@compact('crossorigin', 'as', 'fetchpriority', 'integrity', 'referrerpolicy', 'imagesizes', 'imagesrcset'));

                return [
                    $this->buildLinkHeader($href ?? '', $rel ?? null, $attributes),
                    $this->buildLinkHeader($src ?? '', $rel ?? null, $attributes),
                    $this->buildLinkHeader($data ?? '', $rel ?? null, $attributes),
                ];
            })
            ->filter(function (?Link $value) use ($excludeKeywords) {
                if (! $value) {
                    return false;
                }
                $exclude_keywords = collect($excludeKeywords)->map(function ($keyword) {
                    return preg_quote($keyword);
                });
                if ($exclude_keywords->count() <= 0) {
                    return true;
                }

                return ! preg_match('%('.$exclude_keywords->implode('|').')%i', $value->getHref());
            });

        $event->linkHeaders->addLink($headers->toArray());
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

        return collect(
            $crawler->filter('link:not([rel*="icon"]):not([rel="canonical"]):not([rel="manifest"]):not([rel="alternate"]), script[src]:not([defer]):not([async]), *:not(picture)>img[src]:not([loading="lazy"]), object[data]')
                ->extract(['src', 'href', 'data', 'rel', 'type', 'crossorigin', 'as', 'fetchpriority', 'integrity', 'referrerpolicy', 'imagesizes', 'imagesrcset'])
        );
    }

    /**
     * Build out header string based on asset extension.
     */
    private function buildLinkHeader(string $url, ?string $rel = 'preload', ?array $attributes = []): ?Link
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

        if (! $url) {
            return null;
        }

        $type = collect($linkTypeMap)->first(function ($type, $extension) use ($url) {
            return Str::contains(strtoupper($url), $extension);
        });

        if (! preg_match('%^(https?:)?//%i', $url)) {
            $basePath = config('http3earlyhints.base_path', '/');
            $url = rtrim($basePath.ltrim($url, $basePath), '/');
        }

        if (! in_array($rel, ['preload', 'preconnect'])) {
            $rel = 'preload';
        }

        $link = new Link($rel, $url);

        foreach ($attributes as $key => $value) {
            $link = $link->withAttribute($key, $value);
        }

        if ($rel === 'preconnect' && $url) {
            return $link;
        }

        if (empty($attributes['as'])) {
            $link = $link->withAttribute('as', $type ?? 'fetch');
        }
        if ($type === 'font' && empty($attributes['crossorigin'])) {
            $link = $link->withAttribute('crossorigin', 'anonymous');
        }

        return $link;
    }

    public static function register()
    {
        Event::listen(GenerateEarlyHints::class, static::class);
    }
}
