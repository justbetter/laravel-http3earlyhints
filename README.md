# Early Hints Middleware for Laravel

Early Hints is a HTTP/3 concept which allows the server to send preconnect and preload headers while it's still preparing a response.
This allows the broser to start loading these resources before the server has finished building and sending a response
[See](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/103).

This package aims to provide the _easiest_ experience for adding Early Hints to your responses.
Simply route your requests through the `AddHttp3EarlyHints` middleware and it will automatically create and attach the `Link` headers necessary to implement Early Hints for your CSS, JS and Image assets.

## Installation

You can install the package via composer:
``` bash
$ composer require justbetter/laravel-http3earlyhints
```

Next you must add the `\JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints`-middleware to the kernel. Adding it to the web group is recommeneded as API's do not have assets to push.
```php
// app/Http/Kernel.php

...
protected $middlewareGroups = [
    'web' => [
        ...
        \JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints::class,
        ...
    ],
    ...
];
```

## Publish config

```php
php artisan vendor:publish --provider="JustBetter\Http3EarlyHints\ServiceProvider"
```

**Note:** `send_103` defaults to `false`, this is because it isn't actually supported widely yet. Currently only [FrankenPHP supports Early Hints natively](https://frankenphp.dev/docs/early-hints/).
default behaviour is adding the link headers to the 200 response which e.g. [Cloudflare turns into early hints](https://developers.cloudflare.com/cache/advanced-configuration/early-hints/#generate-early-hints).

## Usage

When you route a request through the `AddHttp3EarlyHints` middleware, the response is scanned for any `link`, `script` or `img` tags that could benefit from being loaded using Early Hints.
These assets will be added to the `Link` header before sending the response to the client. Easy!

**Note:** To push an image asset, it must have one of the following extensions: `bmp`, `gif`, `jpg`, `jpeg`, `png`, `tiff` or `svg` and not have `loading="lazy"`

### Advanced usage

If the automatic detection isn't enough for you, you can listen for GenerateEarlyHints events, and manually add new links.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
