<?php

declare(strict_types=1);

namespace Chiron\FastRoute\Traits;

use Chiron\Routing\Traits\RouteCollectionInterface;
use Chiron\Routing\Traits\RouteCollectionTrait;
use Chiron\Routing\Exception\RouterException;
use Chiron\Pipe\PipelineBuilder;
use Chiron\Routing\UrlMatcherInterface;
use Chiron\Routing\RouteCollection;
use Chiron\Routing\Route;
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

trait PatternsTrait
{
    /**
     * @var array
     */
    // TODO : regarder ici : https://github.com/ncou/router-group-middleware/blob/master/src/Router/Router.php#L25
    // TODO : regarder ici : https://github.com/ncou/php-router-group-middleware/blob/master/src/Router.php#L26
    // TODO : faire un tableau plus simple et ensuite dans le constructeur faire un array walk pour ajouter ces patterns.
    // TODO : on devrait pas utiliser plutot cet regex pour les UUID : '[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}'
    private $patternMatchers = [
        '/{(.+?):number}/'        => '{$1:[0-9]+}',
        '/{(.+?):word}/'          => '{$1:[a-zA-Z]+}',
        '/{(.+?):alphanum_dash}/' => '{$1:[a-zA-Z0-9-_]+}',
        '/{(.+?):slug}/'          => '{$1:[a-z0-9-]+}',
        '/{(.+?):uuid}/'          => '{$1:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}',
    ];



    /*

    ':any' => '[^/]+',
    ':all' => '.*'


    '*'  => '.+?',
    '**' => '.++',


    */

    /*
    //https://github.com/codeigniter4/CodeIgniter4/blob/develop/system/Router/RouteCollection.php#L122

    private $placeholders = [
        'any'      => '.*',
        'segment'  => '[^/]+',
        'alphanum' => '[a-zA-Z0-9]+',
        'num'      => '[0-9]+',
        'alpha'    => '[a-zA-Z]+',
        'hash'     => '[^/]+',
    ];


    */

    /**
     * Add or replace the requirement pattern inside the route path.
     *
     * @param array  $requirements
     * @param string $path
     *
     * @return string
     */
    private function replaceAssertPatterns(array $requirements, string $path): string
    {
        $patternAssert = [];
        foreach ($requirements as $attribute => $pattern) {
            // it will replace {attribute_name} to {attribute_name:$pattern}, work event if there is alreay a patter {attribute_name:pattern_to_remove} to {attribute_name:$pattern}
            // the second regex group (starting with the char ':') will be discarded.
            $patternAssert['/{(' . $attribute . ')(\:.*)?}/'] = '{$1:' . $pattern . '}';
            //$patternAssert['/{(' . $attribute . ')}/'] = '{$1:' . $pattern . '}'; // TODO : réfléchir si on utilise cette regex, dans ce cas seulement les propriétés qui n'ont pas déjà un pattern de défini (c'est à dire une partie avec ':pattern')
        }

        return preg_replace(array_keys($patternAssert), array_values($patternAssert), $path);
    }

    /**
     * Replace word patterns with regex in route path.
     *
     * @param string $path
     *
     * @return string
     */
    private function replaceWordPatterns(string $path): string
    {
        return preg_replace(array_keys($this->patternMatchers), array_values($this->patternMatchers), $path);
    }
}
