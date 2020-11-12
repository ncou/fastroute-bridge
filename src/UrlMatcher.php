<?php

declare(strict_types=1);

namespace Chiron\FastRoute;

use Chiron\Routing\Traits\RouteCollectionInterface;
use Chiron\Routing\Traits\RouteCollectionTrait;
use Chiron\Routing\Exception\RouterException;
use Chiron\Pipe\PipelineBuilder;
use Chiron\Routing\UrlMatcherInterface;
use Chiron\Routing\RouteCollection;
use Chiron\Routing\Route;
use Chiron\Http\Message\RequestMethod;
use Chiron\Routing\RouteGroup;
use Chiron\Routing\MatchingResult;
use Chiron\Routing\RoutingHandler;
use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\Dispatcher as DispatcherInterface;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Chiron\FastRoute\Traits\PatternsTrait;

//https://github.com/zendframework/zend-expressive-fastroute/blob/master/src/FastRouteRouter.php
//https://github.com/Wandu/Router/blob/master/RouteCollection.php

// HEAD Support :
// https://github.com/atanvarno69/router
// https://github.com/slimphp/Slim/blob/4.x/Slim/Routing/FastRouteDispatcher.php#L36

// CACHED ROUTER :
//https://github.com/atanvarno69/router/blob/master/src/CachedRouter.php
//https://github.com/slimphp/Slim/blob/4.x/Slim/Routing/Dispatcher.php#L49
//https://github.com/thephpleague/route/blob/5.x/src/CachedRouter.php
//https://github.com/zendframework/zend-expressive-fastroute/blob/master/src/FastRouteRouter.php#L471
//https://github.com/mezzio/mezzio-fastroute/blob/3.1.x/src/FastRouteRouter.php
//https://github.com/litphp/cached-fast-route/blob/master/src/CachedDispatcher.php#L64
//https://github.com/vanchelo/modxFastRouter/blob/master/core/components/fastrouter/fastrouter.class.php#L240
//https://github.com/abbadon1334/atk4-fastroute/blob/master/src/Router.php#L244

// TODO : il manque head et options dans la phpdoc, et le check sur la collision n'est plus d'actualité !!!!
/**
 * Aggregate routes for the router.
 *
 * This class provides * methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - any
 *
 * A general `route()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods, and will raise an exception when a
 * collision occurs.
 */



// TODO : boucler sur le tableau des routes et mettre dans un tableau le nom de la route et utiliser la méthode ci dessous pour détecter si il y a des doublons de noms pour les routes (il faudra appeller cette méthode à deux endroits, lors de injectRoutes() et lors de getNamedRoute() [car l'injection n'a peut etre pas encore eu lieux!!!!]) :
/*
function array_has_dupes($array): bool {
   return count($array) !== count(array_unique($array));
}*/



// TODO : vérifier comment ca se passe si on ajoute plusieurs fois une route avec le même nom !!!!
final class UrlMatcher implements UrlMatcherInterface
{
    use PatternsTrait;

    /** @var FastRoute\RouteParser */
    private $routeParser;

    /** @var FastRoute\DataGenerator */
    private $routeGenerator;

    /** @var bool */
    // TODO : renommer la variable en "injected"
    private $isInjected = false;

    /** @var RouteCollection */
    private $routeCollection;

    /**
     * Constructor.
     *
     * @param \FastRoute\RouteParser   $parser
     * @param \FastRoute\DataGenerator $generator
     */
    // TODO : créer un constructeur qui prendra en paramétre un routeCollector, ca évitera de faire un appel à setRouteCollector() !!!!
    // TODO : virer le DataGenerator qui est en paramétre et faire un new directement dans le constructeur.
    // TODO : renommer cette variable $routeCollection en $routes une fois qu'on aura fait hériter la classe RouteCollection::class de Iterator et Count !!!!
    public function __construct(RouteCollection $routeCollection)
    {
        $this->routeCollection = $routeCollection;

        $this->routeParser = new RouteParser();
        // build parent route collector
        $this->routeGenerator = new RouteGenerator();

        // TODO utiliser ce bout de code et faire un tableau de pattern dans la classe de ce type ['slug' => 'xxxx', 'number' => 'yyyy']
/*
        array_walk($this->patternMatchers, function ($value, $key) {
            $this->addPatternMatcher($key, $value);
        });*/
    }

    /**
     * Add a convenient pattern matcher to the internal array for use with all routes.
     *
     * @param string $alias
     * @param string $regex
     *
     * @return self
     */
    public function addPatternMatcher(string $alias, string $regex): self
    {
        $pattern = '/{(.+?):' . $alias . '}/';
        $regex = '{$1:' . $regex . '}';

        $this->patternMatchers[$pattern] = $regex;

        return $this;
    }

    public function match(ServerRequestInterface $request): MatchingResult
    {
        // prepare routes
        $this->injectRoutes($request);

        // process routes
        $dispatcher = $this->getDispatcher();

        $httpMethod = $request->getMethod();
        $uri = $request->getUri()->getPath(); //$uri = '/' . ltrim($request->getUri()->getPath(), '/');

        $result = $dispatcher->dispatch($httpMethod, rawurldecode($uri));

        //die(var_dump($result));

        return $result[0] !== DispatcherInterface::FOUND
            ? $this->marshalFailedRoute($result)
            : $this->marshalMatchedRoute($result);
    }

    private function getDispatcher(): DispatcherInterface
    {
        return new RouteDispatcher($this->routeGenerator->getData());
    }

    /**
     * Marshal a routing failure result.
     *
     * If the failure was due to the HTTP method, passes the allowed HTTP
     * methods to the factory.
     */
    private function marshalFailedRoute(array $result): MatchingResult
    {
        if ($result[0] === DispatcherInterface::METHOD_NOT_ALLOWED) {
            return MatchingResult::fromRouteFailure($result[1]);
        }

        return MatchingResult::fromRouteFailure(RequestMethod::ANY);
    }

    /**
     * Marshals a route result based on the results of matching and the current HTTP method.
     */
    private function marshalMatchedRoute(array $result): MatchingResult
    {
        $route = $result[1];
        $params = $result[2];

        return MatchingResult::fromRoute($route, $params);
    }

    /**
     * Prepare all routes, build name index and filter out none matching
     * routes before being passed off to the parser.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    // TODO : passer uniquement en paramétre un UriInterface plutot que toute la Request !!!!
    // TODO : il faudrait faire une vérification si il n'y a pas des routes en double avec le même "name", car cela va nous poser des soucis lorsqu'on va essayer de la récupérer en utilisant la méthode ->getNamedRoute() !!!!
    private function injectRoutes(ServerRequestInterface $request): void
    {
        // only inject routes once.
        if ($this->isInjected) {
            return;
        }

        // TODO : améliorer le code en utilisant cet exemple : https://github.com/yiisoft/router-fastroute/blob/93de4e7af1ad4a4831a9d8986d0b3d4fcf17bfe2/src/UrlMatcher.php#L274
        // Regex pour splitter une url : https://www.admfactory.com/split-url-into-components-using-regex/
        // TODO : améliorer le code pour la vérification sur le getScheme / host et port : https://github.com/thephpleague/route/blob/5.x/src/Dispatcher.php#L69
        foreach ($this->routeCollection as $route) {
            // check for scheme condition
            if (! is_null($route->getScheme()) && $route->getScheme() !== $request->getUri()->getScheme()) {
                continue;
            }
            // check for domain condition
            if (! is_null($route->getHost()) && $route->getHost() !== $request->getUri()->getHost()) {
                continue;
            }
            // check for port condition
            if (! is_null($route->getPort()) && $route->getPort() !== $request->getUri()->getPort()) {
                continue;
            }

            $routePath = $this->replaceAssertPatterns($route->getRequirements(), $route->getPath());
            $routePath = $this->replaceWordPatterns($routePath);

            $this->injectRoute($route, $route->getAllowedMethods(), $routePath);
        }

        $this->isInjected = true;
    }

    /**
     * Adds a route to the collection.
     *
     * The syntax used in the $route string depends on the used route parser.
     *
     * @param string   $routeId
     * @param string[] $httpMethod
     * @param string   $routePath
     *
     * @throws RouterException If two routes match the same url+method, or if the route is shadowed by a previous route.
     */
    private function injectRoute(Route $route, array $httpMethod, string $routePath): void
    {
        // TODO : attention car la méthode "parse()" peut aussi retourner une FastRoute\BadRouteException qu'il faudrait catcher !!!!   => https://github.com/nikic/FastRoute/blob/4406c3b79b3cce4e906fa9c11cd564d2828f6257/src/RouteParser/Std.php#L40
        $routeDatas = $this->routeParser->parse($routePath);
        foreach ($httpMethod as $method) {
            foreach ($routeDatas as $routeData) {
                try {
                    $this->routeGenerator->addRoute($method, $routeData, $route);
                } catch (\FastRoute\BadRouteException $e) {
                    // TODO : ne plus utiliser l'exception RouterException, mais une exception générique type InvalidArgumentException. ou alors renommer l'exception en RoutingException.
                    // TODO : créer une exception génrique BadRouteException dans le package "chiron/routing"
                    throw new RouterException($e->getMessage());
                }
            }
        }
    }
}
