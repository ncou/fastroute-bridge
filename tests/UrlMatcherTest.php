<?php

declare(strict_types=1);

namespace Chiron\Tests\FastRoute;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Chiron\Routing\Route;
use Chiron\FastRoute\FastRouteRouter as Router;
use PHPUnit\Framework\TestCase;
use Chiron\FastRoute\UrlMatcher;
use Chiron\Routing\Map;
use Chiron\Routing\UrlMatcherInterface;
use Chiron\Container\Container;
use Chiron\Routing\Exception\RouterException;

class UrlMatcherTest extends TestCase
{
    private function createUrlMatcher(array $routes): UrlMatcherInterface
    {
        $map = new Map();
        $map->setContainer(new Container());

        foreach ($routes as $route) {
            $map->addRoute($route);
        }

        return new UrlMatcher($map);
    }


    public function testDisallowedHEADMethod(): void
    {
        $routes = [
            Route::get('/site/index'),
            Route::post('/site/index'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('HEAD', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame(['GET', 'POST'], $result->getAllowedMethods());
    }


}
