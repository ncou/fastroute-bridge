<?php

declare(strict_types=1);

namespace Chiron\FastRoute;

use FastRoute\RouteParser\Std as RouteParser;
use Chiron\Routing\Map;
use Chiron\Routing\UrlGeneratorInterface;
use Psr\Http\Message\UriInterface;
use InvalidArgumentException;
use Chiron\FastRoute\Traits\PatternsTrait;

//https://github.com/symfony/routing/blob/master/Generator/UrlGenerator.php#L324
//https://github.com/illuminate/routing/blob/master/RouteUrlGenerator.php
//https://github.com/yiisoft/router-fastroute/blob/master/src/FastRoute.php#L227
//https://github.com/yiisoft/router/blob/master/src/UrlGeneratorInterface.php

final class UrlGenerator implements UrlGeneratorInterface
{
    use PatternsTrait;

    /** @var FastRoute\RouteParser */
    private $routeParser;

    /** @var Map */
    private $map;

    /**
     * Characters that should not be URL encoded.
     *
     * @var array
     */
    private $dontEncode = [
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
        '%3F' => '?',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    public function __construct(Map $map)
    {
        $this->map = $map;
        $this->routeParser = new RouteParser();
    }

    public function absoluteUrlFor(UriInterface $uri, string $routeName, array $substitutions = [], array $queryParams = []): string
    {
        $path = $this->relativeUrlFor($routeName, $substitutions, $queryParams);

        $scheme = $uri->getScheme();
        $authority = $uri->getAuthority();
        $protocol = ($scheme ? $scheme . ':' : '') . ($authority ? '//' . $authority : '');

        return $protocol . $path;
    }

    /**
     * Build the path for a named route excluding the base url.
     *
     * @param string $routeName     Route name
     * @param array  $substitutions Named argument replacement data
     * @param array  $queryParams   Optional query string parameters
     *
     * @throws RouteNotFoundException If named route does not exist.
     * @throws InvalidArgumentException If required data not provided.
     *
     * @return string
     */
    public function relativeUrlFor(string $routeName, array $substitutions = [], array $queryParams = []): string
    {
        $route = $this->map->getRoute($routeName);

        $routePath = $this->replaceAssertPatterns($route->getRequirements(), $route->getPath());
        $routePath = $this->replaceWordPatterns($routePath);

        $substitutions = array_replace($route->getDefaults(), $substitutions);

        return $this->generate($routePath, $substitutions, $queryParams);
    }

    /**
     * Build the path for a named route excluding the base path.
     *
     * @param string $routePath     Route path pattern
     * @param array  $substitutions Named argument replacement data
     * @param array  $queryParams   Optional query string parameters
     *
     * @throws InvalidArgumentException If parameter is invalid, or segment data is missing.
     *
     * @return string
     */
    // TODO : ajouter la gestion des segments en plus des query params ???? https://github.com/ellipsephp/url-generator/blob/master/src/UrlGenerator.php#L42
    // TODO : regarder si on peut améliorer le code => https://github.com/zendframework/zend-expressive-fastroute/blob/master/src/FastRouteRouter.php#L239
    // TODO ; utiliser ce bout de code : https://github.com/slimphp/Slim/blob/4.x/Slim/Routing/RouteParser.php#L42
    // TODO : améliorer le code avec cette partie là =>   https://github.com/illuminate/routing/blob/master/RouteUrlGenerator.php#L77
    // https://github.com/zendframework/zend-expressive-fastroute/blob/master/src/FastRouteRouter.php#L239
    //https://github.com/illuminate/routing/blob/master/RouteUrlGenerator.php#L77
    private function generate(string $routePath, array $substitutions = [], array $queryParams = []): string
    {
        // TODO : attention il faut lui passer la route en paramétre de la fonction generate() car on doit aussi utiliser les paramétres par défaut pour générer l'URI => https://github.com/yiisoft/router-fastroute/blob/master/src/FastRoute.php#L231

        // TODO : autre exemple avec les paramétres par défaut => https://github.com/symfony/routing/blob/master/Generator/UrlGenerator.php#L173

        $routeDatas = $this->routeParser->parse($routePath);

        // $routeDatas is an array of all possible routes that can be made. There is
        // one routedata for each optional parameter plus one for no optional parameters.
        //
        // The most specific is last, so we look for that first.
        $routeDatas = array_reverse($routeDatas);

        $segments = [];
        $segmentName = '';

        // One route pattern can correspond to multiple routes if it has optional parts
        foreach ($routeDatas as $routeData) {
            foreach ($routeData as $item) {
                if (is_string($item)) {
                    // this segment is a static string
                    $segments[] = $item;

                    continue;
                }

                // This segment has a parameter: first element is the name
                if (! array_key_exists($item[0], $substitutions)) {
                    // we don't have a data element for this segment: cancel
                    // testing this routeData item, so that we can try a less
                    // specific routeData item.
                    $segments = [];
                    $segmentName = $item[0];

                    break;
                }

                // TODO : faire aussi une vérification avec la valeur "assert/requirement" qui est portée dans l'objet Route.
                // Check substitute value with regex
                $pattern = str_replace('~', '\~', $item[1]);
                if (! preg_match('~^' . $pattern . '$~', (string) $substitutions[$item[0]])) {
                    throw new InvalidArgumentException(sprintf(
                        'Parameter value for [%s] did not match the regex `%s`',
                        $item[0],
                        $item[1]
                    ));
                }

                $segments[] = $substitutions[$item[0]];
            }
            if (! empty($segments)) {
                // we found all the parameters for this route data, no need to check
                // less specific ones
                break;
            }
        }

        if (empty($segments)) {
            throw new InvalidArgumentException('Missing data for URL segment: ' . $segmentName);
        }

        $url = implode('', $segments);

        if ($queryParams) {
            // TODO : améliorer le code avec ca : https://github.com/illuminate/routing/blob/master/RouteUrlGenerator.php#L255 et ca : https://github.com/illuminate/support/blob/master/Arr.php#L599
            //$url .= '?' . http_build_query($queryParams);
            $url = $this->addQueryString($url, $queryParams);
        }

        // We will encode the URI and prepare it for returning to the developer.
        $url = strtr(rawurlencode($url), $this->dontEncode);

        return $url;
    }

    /**
     * Add a query string to the URI.
     *
     * @param string $url
     * @param array  $parameters
     *
     * @return mixed|string
     */
    private function addQueryString(string $url, array $parameters): string
    {
        // If the URI has a fragment we will move it to the end of this URI since it will
        // need to come after any query string that may be added to the URL else it is
        // not going to be available. We will remove it then append it back on here.
        if (! is_null($fragment = parse_url($url, PHP_URL_FRAGMENT))) { // TODO : utiliser la méthode suivante qui préserve les points dans les variables ???? https://github.com/symfony/symfony/blob/4da4f50837dac448e007688ea18a153bfe756325/src/Symfony/Component/HttpFoundation/HeaderUtils.php#L199
        // TODO : utiliser aussi cette exemple : https://github.com/guzzle/guzzle/issues/21
        // TODO : utiliser : https://github.com/guzzle/psr7/blob/ad1de77a65b751d598ced37747bf4c17d457fbc9/src/functions.php#L509
            $url = preg_replace('/#.*/', '', $url);
        }
        $url .= $this->getRouteQueryString($parameters);

        return is_null($fragment) ? $url : $url . "#{$fragment}";
    }

    /**
     * Get the query string for a given route.
     *
     * @param array $parameters
     *
     * @return string
     */
    // TODO utiliser un helper de type Arr::query    => exemple : https://github.com/top-think/think-helper/blob/3.0/src/helper/Arr.php#L603
    private function getRouteQueryString(array $parameters): string
    {
        // TODO : utiliser https://github.com/guzzle/psr7/blob/ad1de77a65b751d598ced37747bf4c17d457fbc9/src/functions.php#L560
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return '?' . $query;
    }

    /*
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
    /*
    //https://github.com/symfony/routing/blob/master/Generator/UrlGenerator.php#L324
    public static function getRelativePath($basePath, $targetPath)
    {
        if ($basePath === $targetPath) {
            return '';
        }
        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);
        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }
        $targetDirs[] = $targetFile;
        $path = str_repeat('../', \count($sourceDirs)).implode('/', $targetDirs);
        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }*/
}
