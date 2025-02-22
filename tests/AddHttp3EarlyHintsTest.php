<?php

declare(strict_types=1);

namespace JustBetter\Http3EarlyHints\Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use JustBetter\Http3EarlyHints\Middleware\AddHttp3EarlyHints;

class AddHttp3EarlyHintsTest extends TestCase
{
    protected ?AddHttp3EarlyHints $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AddHttp3EarlyHints;
        parent::setUp();
    }

    public function getNewRequest(): Request
    {
        return new Request(server: $_SERVER);
    }

    /** @test */
    public function it_will_not_exceed_size_limit(): void
    {
        $request = $this->getNewRequest();

        $limit = 75;
        $response = $this->middleware->handle($request, $this->getNext('pageWithCssAndJs'), $limit);

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertTrue(strlen($response->headers->get('link')) <= $limit);
        $this->assertCount(1, explode(',', $response->headers->get('link')));
    }

    /** @test */
    public function it_will_not_add_excluded_asset(): void
    {
        $request = $this->getNewRequest();
        config(['http3earlyhints' => ['exclude_keywords' => ['thing']]]);
        $response = $this->middleware->handle($request, $this->getNext('pageWithCssAndJs'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertTrue(! Str::contains($response->headers, 'thing'));
        $this->assertCount(1, explode(',', $response->headers->get('link')));
    }

    /** @test */
    public function it_will_not_modify_a_response_with_no_server_push_assets(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithoutAssets'));

        $this->assertFalse($this->isServerPushResponse($response));
    }

    /** @test */
    public function it_will_return_a_css_link_header_for_css(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithCss'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="style"', $response->headers->get('link'));
    }

    /** @test */
    public function it_will_return_a_js_link_header_for_js(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithJs'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="script"', $response->headers->get('link'));
    }

    /** @test */
    public function it_will_return_a_js_link_header_with_crossorigin_for_js_module(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithJsModule'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="script"', $response->headers->get('link'));
        $this->assertStringContainsString('crossorigin="anonymous"', $response->headers->get('link'));
    }

    /** @test */
    public function it_will_return_a_js_link_header_with_use_credentials_crossorigin_for_js_module(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithJsCrossoriginModule'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="script"', $response->headers->get('link'));
        $this->assertStringContainsString('crossorigin="use-credentials"', $response->headers->get('link'));
    }

    /** @test */
    public function it_will_return_an_image_link_header_for_images(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithImages'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="image"', $response->headers->get('link'));
        $this->assertCount(7, explode(',', $response->headers->get('link')));
    }

    /** @test */
    public function it_will_return_an_image_link_header_for_svg_objects(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithSVGObject'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringEndsWith('as="image"', $response->headers->get('link'));
        $this->assertCount(1, explode(',', $response->headers->get('link')));
    }

    /** @test */
    public function it_will_return_a_fetch_link_header_for_fetch(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithFetchPreload'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringContainsString('</api/resource>; rel="preload"', $response->headers->get('link'));
        $this->assertStringEndsWith('as="script"', $response->headers->get('link'));
    }

    /** @test */
    public function it_returns_well_formatted_link_headers(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithCss'));

        $this->assertEquals('</css/test.css>; rel="preload"; as="style"', $response->headers->get('link'));
    }

    /** @test */
    public function it_will_return_correct_push_headers_for_multiple_assets(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithCssAndJs'));

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertTrue(Str::contains($response->headers, 'style'));
        $this->assertTrue(Str::contains($response->headers, 'script'));
        $this->assertCount(2, explode(',', $response->headers->get('link')));
    }

    /** @test */
    public function it_will_not_return_a_push_header_for_inline_js(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithJsInline'));

        $this->assertFalse($this->isServerPushResponse($response));
    }

    /** @test */
    public function it_will_not_return_a_push_header_for_icons(): void
    {
        $request = $this->getNewRequest();

        $response = $this->middleware->handle($request, $this->getNext('pageWithFavicon'));

        $this->assertFalse($this->isServerPushResponse($response));
    }

    /** @test */
    public function it_will_append_to_header_if_already_present(): void
    {
        $request = $this->getNewRequest();

        $next = $this->getNext('pageWithCss');

        $response = $this->middleware->handle($request, function ($request) use ($next) {
            $response = $next($request);
            $response->headers->set('Link', '<https://example.com/en>; rel="alternate"; hreflang="en"');

            return $response;
        });

        $this->assertTrue($this->isServerPushResponse($response));
        $this->assertStringStartsWith('<https://example.com/en>; rel="alternate"; hreflang="en",', $response->headers->get('link'));
        $this->assertStringEndsWith('as="style"', $response->headers->get('link'));
    }

    protected function getNext(string $pageName): Closure
    {
        return fn ($request) => new Response(
            content: $this->getHtml($pageName),
        );
    }

    protected function getHtml(string $pageName): string
    {
        return file_get_contents(
            filename: __DIR__."/fixtures/{$pageName}.html",
        );
    }

    private function isServerPushResponse($response)
    {
        return $response->headers->has('Link');
    }
}
