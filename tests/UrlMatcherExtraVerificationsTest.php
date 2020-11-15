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

/**
 * Test the setPort/setScheme/setHost extra verification values
 */
class UrlMatcherExtraVerificationsTest extends TestCase
{
    private $request;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', new Uri('http://example.com:8080/something'));
    }

    private function createUrlMatcher(array $routes): UrlMatcherInterface
    {
        $routeCollection = new RouteCollection(new Container());

        foreach ($routes as $route) {
            $routeCollection->addRoute($route);
        }

        return new UrlMatcher($routeCollection);
    }

    public function testMatchingSuccessOnMatchedScheme(): void
    {
        $routes = [
            Route::get('/something')->setScheme('http'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isSuccess());
    }

    public function testMatchingSuccessOnMatchedHost(): void
    {
        $routes = [
            Route::get('/something')->setHost('example.com'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isSuccess());
    }

    public function testMatchingSuccessOnMatchedPort(): void
    {
        $routes = [
            Route::get('/something')->setPort(8080),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isSuccess());
    }

    public function testMatchingFailOnMismatchedScheme(): void
    {
        $routes = [
            Route::get('/something')->setScheme('https'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isFailure());
    }

    public function testMatchingFailOnMismatchedHost(): void
    {
        $routes = [
            Route::get('/something')->setHost('sub.example.com'),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isFailure());
    }

    public function testMatchingFailOnMismatchedPort(): void
    {
        $routes = [
            Route::get('/something')->setPort(443),
        ];

        $matchingResult = $this->createUrlMatcher($routes)->match($this->request);

        $this->assertTrue($matchingResult->isFailure());
    }

}
