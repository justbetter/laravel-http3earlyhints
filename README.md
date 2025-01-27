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
### Laravel <11
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

### Laravel >=11
```php
// bootstrap/app.php

...
->withMiddleware(function (Middleware $middleware) {
    ...
    $middleware->appendToGroup('web', [
        \JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints::class,
    ]);
    // Or
    // $middleware->append(\JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints::class);
    ...
})
...
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

**Note:** To push an image asset, it must have one of the following extensions: `bmp`, `gif`, `jpg`, `jpeg`, `png`, `svg`, `tiff` or `webp` and not have `loading="lazy"`

### Advanced usage

If the automatic detection isn't enough for you, you can listen for the `GenerateEarlyHints` event, and manually add/remove/change new links.

#### Detailed default behaviour

The information on [usage](#usage) is simplified, there are many checks done to make sure we don't preload the wrong things.

Early hints only support rel=preconnect and rel=preload [source](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/103#browser_compatibility)

We automatically transform any rel that is not `preconnect` or `preload` into `preload`, so your `<link rel="modulepreload" href="app.js">` will get preloaded with early hints. And get more detailed information once your server starts sending it's response.

##### Link

Any link elements which do **not** have rel=
- icon
- canonical
- manifest
- alternative

##### Script

Script tags will automatically get preloaded **if** it does not have an `async` or `defer` attribute attached to it.

##### Img

Img tags will automatically get preloaded when it does not have `loading="lazy"` and it does not exist within a picture tag.

If it is within a picture tag we may be dealing with mutliple `srcset`s or `type`s, and thus cannot determine which file the browser will need.
So we will not preload these images.

##### Object

If your html object tag contains `data=""` it will preload it.

##### Nonce

While the early hints module does support sending [nonce](https://laravel.com/docs/11.x/vite#content-security-policy-csp-nonce) across as well, we recommend against it. And use [integrity](https://laravel.com/docs/11.x/vite#subresource-integrity-sri) instead.

Without hardcoding the nonce
[Vite::useCspNonce($nonce);](https://laravel.com/docs/11.x/vite#content-security-policy-csp-nonce:~:text=Vite%3A%3AuseCspNonce(%24nonce)%3B)
sending this in early hints will be useless as each request will send early hints with a stale nonce.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
