<?php

declare(strict_types=1);

namespace Chiron\Tests\FastRoute;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Chiron\Routing\Route;
use Chiron\FastRoute\FastRouteRouter as Router;
use PHPUnit\Framework\TestCase;
use Chiron\FastRoute\UrlMatcher;
use Chiron\Routing\RouteCollection;
use Chiron\Routing\UrlMatcherInterface;
use Chiron\Container\Container;
use Chiron\Routing\Exception\RouterException;

class UrlMatcherExceptionsTest extends TestCase
{
    private $request;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', new Uri('/foo'));
    }

    private function createUrlMatcher(array $routes): UrlMatcherInterface
    {
        $routeCollection = new RouteCollection(new Container());

        foreach ($routes as $route) {
            $routeCollection->addRoute($route);
        }

        return new UrlMatcher($routeCollection);
    }

    public function testDuplicateVariableNameError()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot use the same placeholder "test" twice');

        $routes = [
            Route::get('/foo/{test}/{test:\d+}'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testDuplicateVariableRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot register two routes matching "/user/([^/]+)" for method "GET"');

        $routes = [
            Route::get('/user/{id}'), // oops, forgot \d+ restriction ;-)
            Route::get('/user/{name}'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testDuplicateStaticRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot register two routes matching "/user" for method "GET"');

        $routes = [
            Route::get('/user'),
            Route::get('/user'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testShadowedStaticRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Static route "/user/nikic" is shadowed by previously defined variable route "/user/([^/]+)" for method "GET"');

        $routes = [
            Route::get('/user/{name}'),
            Route::get('/user/nikic'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

    }

    public function testCapturing()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Regex "(en|de)" for parameter "lang" contains a capturing group');

        $routes = [
            Route::get('/{lang:(en|de)}'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    /**
     * @dataProvider provideTestParseError
     */
    public function testParseError(string $routeString, string $expectedExceptionMessage): void
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $routes = [
            Route::get($routeString),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    /**
     * @return string[][]
     */
    public function provideTestParseError(): array
    {
        return [
            [
                '/test[opt',
                "Number of opening '[' and closing ']' does not match",
            ],
            [
                '/test[opt[opt2]',
                "Number of opening '[' and closing ']' does not match",
            ],
            [
                '/testopt]',
                "Number of opening '[' and closing ']' does not match",
            ],
            [
                '/test[]',
                'Empty optional part',
            ],
            [
                '/test[[opt]]',
                'Empty optional part',
            ],
            [
                '[[test]]',
                'Empty optional part',
            ],
            [
                '/test[/opt]/required',
                'Optional segments can only occur at the end of a route',
            ],
        ];
    }
}
