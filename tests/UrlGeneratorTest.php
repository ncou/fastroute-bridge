<?php

declare(strict_types=1);

namespace Chiron\Tests\FastRoute;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use Chiron\Routing\Route;
use Chiron\Routing\RouteCollection;
use Chiron\Routing\UrlGeneratorInterface;
use Chiron\FastRoute\FastRoute as Router;
use PHPUnit\Framework\TestCase;
use Chiron\Routing\Target\TargetFactory;
use Chiron\Container\Container;
use Chiron\FastRoute\UrlGenerator;
use Chiron\Routing\Exception\RouteNotFoundException;

class UrlGeneratorTest extends TestCase
{
    private function createUrlGenerator(array $routes): UrlGeneratorInterface
    {
        $routeCollection = new RouteCollection(new Container());

        foreach ($routes as $route) {
            $routeCollection->addRoute($route);
        }

        return new UrlGenerator($routeCollection);
    }

    public function testSimpleRouteGenerated(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('index');

        $this->assertEquals('/home/index', $url);
    }

    public function testRouteWithoutNameNotFound(): void
    {
        $routes = [
            Route::get('/home/index'),
            Route::get('/index'),
            Route::get('index'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectException(RouteNotFoundException::class);
        $urlGenerator->relativeUrlFor('index');
    }

    public function testParametersSubstituted(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{text:~[\w]+}#{tag:\w+}')->name('view'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('view', ['id' => 100, 'tag' => 'chiron', 'text' => '~test']);

        $this->assertEquals('/view/100/~test#chiron', $url);
    }

    public function testParametersWithoutPatternIsSubstituted(): void
    {
        $routes = [
            Route::get('/view/{name}')->name('view'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('view', ['name' => 'john']);

        $this->assertEquals('/view/john', $url);
    }

    public function testParametersWithDefaultValueIsSubstituted(): void
    {
        $routes = [
            Route::get('/view/{name}')->name('view')->setDefault('name', 'john'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('view');

        $this->assertEquals('/view/john', $url);
    }

    public function testParametersWithDefaultValueIsOverriddentAndSubstituted(): void
    {
        $routes = [
            Route::get('/view/{name}')->name('view')->setDefault('name', 'john'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('view', ['name' => 'tony']);

        $this->assertEquals('/view/tony', $url);
    }

    public function testOptionalParametersWithDefaultValueIsSubstituted(): void
    {
        $routes = [
            Route::get('/view/[{name}]')->name('view')->setDefault('name', 'john'),
        ];
        $url = $this->createUrlGenerator($routes)->relativeUrlFor('view');

        $this->assertEquals('/view/john', $url);
    }


    public function testExceptionThrownIfParameterPatternUsingAliasDoesntMatch(): void
    {
        $routes = [
            Route::get('/view/{id:number}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `[0-9]+`');
        $urlGenerator->relativeUrlFor('view', ['id' => 'text']);
    }


    public function testExceptionThrownIfParameterPatternUsingRequirementsDoesntMatch(): void
    {
        $routes = [
            Route::get('/view/{id}')->name('view')->setRequirement('id', '\d+'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `\d+`');
        $urlGenerator->relativeUrlFor('view', ['id' => 'text']);
    }

    public function testExceptionThrownIfParameterPatternDoesntMatch(): void
    {
        $routes = [
            Route::get('/view/{id:\w+}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `\w+`');
        $urlGenerator->relativeUrlFor('view', ['id' => null]);
    }

    public function testExceptionThrownIfAnyParameterIsMissing(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{value}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Missing data for URL segment: value');
        $urlGenerator->relativeUrlFor('view', ['id' => 123]);
    }

    public function testExtraParametersAddedAsQueryString(): void
    {
        $routes = [
            Route::get('/test/{name}')->name('test')
        ];

        $url = $this->createUrlGenerator($routes)->relativeUrlFor('test', ['name' => 'post'], ['id' => 12, 'sort' => 'asc']);
        $this->assertEquals('/test/post?id=12&sort=asc', $url);
    }
}
