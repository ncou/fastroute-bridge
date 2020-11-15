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
    private $handler;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', new Uri('/foo'));
        $this->handler = function () {};
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
            Route::get('/foo/{test}/{test:\d+}')->to($this->handler),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testDuplicateVariableRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot register two routes matching "/user/([^/]+)" for method "GET"');

        $routes = [
            Route::get('/user/{id}')->to($this->handler), // oops, forgot \d+ restriction ;-)
            Route::get('/user/{name}')->to($this->handler),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testDuplicateStaticRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Cannot register two routes matching "/user" for method "GET"');

        $routes = [
            Route::get('/user')->to($this->handler),
            Route::get('/user')->to($this->handler),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }

    public function testShadowedStaticRoute()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Static route "/user/nikic" is shadowed by previously defined variable route "/user/([^/]+)" for method "GET"');

        $routes = [
            Route::get('/user/{name}')->to($this->handler),
            Route::get('/user/nikic')->to($this->handler),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

    }

    public function testCapturing()
    {
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Regex "(en|de)" for parameter "lang" contains a capturing group');

        $routes = [
            Route::get('/{lang:(en|de)}')->to($this->handler),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);
    }
}
