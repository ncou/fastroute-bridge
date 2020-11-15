<?php

declare(strict_types=1);

namespace Chiron\FastRoute\Provider;

//https://github.com/userfrosting/UserFrosting/blob/master/app/system/ServicesProvider.php
//https://github.com/slimphp/Slim/blob/3.x/Slim/DefaultServicesProvider.php

use Chiron\Container\Container;
use Chiron\Container\InvokerInterface;
use Chiron\Core\Container\Provider\ServiceProviderInterface;
use Chiron\Kernel;
use Chiron\FastRoute\UrlMatcher;
use Chiron\FastRoute\UrlGenerator;
use Chiron\Routing\UrlMatcherInterface;
use Chiron\Routing\UrlGeneratorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Chiron\Config\ConfigManager;
use Chiron\Container\BindingInterface;

/**
 * Chiron FastRoute services provider.
 */
class FastRouteServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Chiron FastRoute services.
     *
     * @param BindingInterface $binder
     */
    public function register(BindingInterface $binder): void
    {
        // TODO : attention on risque d'avoir un probléme si on utilise un mode singleton pour le UrlMatcher, car je pense que si on utilise RoadRunner (donc on conserve l'instance PHP pour toutes les request), dans le cas ou on a mis une restriction sur la Route pour du Scheme/Host ou Port on n'ajoute pas ces Routes dans le moteur de FastRoute, si on conserve le singleton on ne pourra donc jamais les insérer dans le cas ou la 1ere request est un http et qu'un https ast attendu, si la 2eme request est un http le singleton va retourner l'instance précédente de fastroute qui n'aura pas eu catte Route d'injectée !!!!
        $binder->singleton(UrlMatcherInterface::class, UrlMatcher::class);
        $binder->singleton(UrlGeneratorInterface::class, UrlGenerator::class);
    }
}
