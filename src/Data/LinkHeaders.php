<?php

namespace JustBetter\Http3EarlyHints\Data;

use Fig\Link\GenericLinkProvider;
use Fig\Link\Link;
use Illuminate\Support\Arr;
use Psr\Link\EvolvableLinkInterface;
use Psr\Link\EvolvableLinkProviderInterface;
use Psr\Link\LinkInterface;

class LinkHeaders
{
    private EvolvableLinkProviderInterface $linkProvider;

    public function __construct(?EvolvableLinkProviderInterface $linkProvider = null)
    {
        $this->linkProvider = $linkProvider ?? new GenericLinkProvider();
    }

    public function getLinkProvider(): EvolvableLinkProviderInterface
    {
        return $this->linkProvider;
    }

    public function setLinkProvider(EvolvableLinkProviderInterface $linkProvider): static
    {
        $this->linkProvider = $linkProvider;

        return $this;
    }

    public function addLink(EvolvableLinkInterface|string|array $uri, string|array|null $rel = null, null|array $attributes = []): static
    {
        if (is_array($uri)) {
            foreach ($uri as $data) {
                $data = Arr::Wrap($data);
                $this->addLink(...$data);
            }
            return $this;
        }

        if ($uri instanceof EvolvableLinkInterface) {
            $this->setLinkProvider($this->getLinkProvider()->withLink($uri));
            return $this;
        }

        if ($rel === null) {
            return $this;
        }
        $link = new Link('', $uri);

        if (\is_string($rel)) {
            $rel = [$rel];
        }

        foreach ($rel as $value) {
            $link = $link->withRel($value);
        }

        foreach ($attributes as $key => $value) {
            $link = $link->withAttribute($key, $value);
        }

        $this->setLinkProvider($this->getLinkProvider()->withLink($link));

        return $this;
    }

    public function addFromString(string $link) : static
    {
        $links = explode(',', trim($link));
        foreach ($links as $link) {
            $parts = explode('; ', trim($link));
            $uri = trim(array_shift($parts), '<>');
            $rel = null;
            $attributes = [];
            foreach($parts as $part) {
                preg_match('/(?<key>[^=]+)(?:="?(?<value>.*)"?)?/', trim($part), $matches);
                $key = $matches['key'];
                $value = $matches['value'] ?? null;

                if($key === 'rel') {
                    $rel = $value;
                    continue;
                }
                $attributes[$key] = $value ?? true;
            }

            $this->addLink($uri, $rel, $attributes);
        }

        return $this;
    }

    public function makeUnique()
    {
        $handledHashes = [];

        foreach ($this->getLinkProvider()->getLinks() as $link) {
            $hash = md5(serialize($link));
            if (!in_array($hash, $handledHashes)) {
                $handledHashes[] = $hash;
                continue;
            }

            $this->setLinkProvider($this->getLinkProvider()->withoutLink($link));
        }

        return $this;
    }

    public function __toString()
    {
        return trim(collect($this->getLinkProvider()->getLinks())
            ->map([static::class, 'linkToString'])
            ->filter()
            ->implode(','));
    }

    public static function linkToString(LinkInterface $link)
    {
        if ($link->isTemplated()) {
            return;
        }

        $attributes = ['', sprintf('rel="%s"', implode(' ', $link->getRels()))];

        foreach ($link->getAttributes() as $key => $value) {
            if (\is_array($value)) {
                foreach ($value as $v) {
                    $attributes[] = sprintf('%s="%s"', $key, $v);
                }

                continue;
            }

            if (!\is_bool($value)) {
                $attributes[] = sprintf('%s="%s"', $key, $value);

                continue;
            }

            if ($value === true) {
                $attributes[] = $key;
            }
        }

        return sprintf('<%s>%s', $link->getHref(), implode('; ', $attributes));
    }
}
