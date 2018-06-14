<?php

namespace Gos\Bundle\PubSubRouterBundle\DependencyInjection\CompilerPass;

use Gos\Bundle\PubSubRouterBundle\Cache\PhpFileCacheDecorator;
use Gos\Bundle\PubSubRouterBundle\Command\DebugRouterCommand;
use Gos\Bundle\PubSubRouterBundle\DependencyInjection\Configuration;
use Gos\Bundle\PubSubRouterBundle\Generator\Generator;
use Gos\Bundle\PubSubRouterBundle\Loader\YamlFileLoader;
use Gos\Bundle\PubSubRouterBundle\Matcher\Matcher;
use Gos\Bundle\PubSubRouterBundle\Tokenizer\TokenizerCacheDecorator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RouterCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $processor = new Processor();
        $configs = $processor->processConfiguration(new Configuration(), $container->getExtensionConfig('gos_pubsub_router'));

        $configs['loaders'][] = '@'.YamlFileLoader::class;
        $container->setParameter('gos_pubsub_registered_routers', array_keys($configs['routers']));

        $debugCmdDef = $container->getDefinition(DebugRouterCommand::class);

        //Replace default tokenizer by the decorated tokenizer
        $container->setAlias('gos_pubsub_router.tokenizer', TokenizerCacheDecorator::class);

        foreach ($configs['routers'] as $name => $routerConf) {

            //RouteCollection
            $collectionServiceName = 'gos_pubsub_router.collection.' . $name;
            $collectionDef = new Definition('Gos\Bundle\PubSubRouterBundle\Router\RouteCollection');
            $container->setDefinition($collectionServiceName, $collectionDef);

            //Matcher
            $matcherDef = $container->getDefinition(Matcher::class);
            $matcherDef
                ->setClass($configs['matcher_class'])
                ->addMethodCall('setCollection', [new Reference($collectionServiceName)]);

            //Generator
            $generatorDef = $container->getDefinition(Generator::class);
            $generatorDef
                ->setClass($configs['generator_class'])
                ->addMethodCall('setCollection', [new Reference($collectionServiceName)]);

            //RouterLoader
            $routeLoaderServiceName = 'gos_pubsub_router.loader.' . $name;
            $routeLoaderDef = new Definition($configs['route_loader_class']);
            /**
            * Make it public for symfony 4
            * @author Randy Téllez Galán <kronhyx@gmail.com>
            */
            $routeLoaderDef->setPublic(true);
            
            $routeLoaderDef->setArguments([
                new Reference($collectionServiceName),
                new Reference(PhpFileCacheDecorator::class),
                $name,
            ]);

            foreach ($routerConf['resources'] as $resource) {
                $routeLoaderDef->addMethodCall('addResource', array($resource));
            }

            foreach ($configs['loaders'] as $loaderRef) {
                $routeLoaderDef->addMethodCall('addLoader', [new Reference(ltrim($loaderRef, '@'))]);
            }

            $container->setDefinition($routeLoaderServiceName, $routeLoaderDef);

            //Router context
            $contextConf = $routerConf['context'];
            $routerContextServiceName = 'gos_pubsub_router.context.' . $name;
            $routerContextDef = new Definition('Gos\Bundle\PubSubRouterBundle\Router\RouterContext');
            $routerContextDef->addMethodCall('setTokenSeparator', [$contextConf['tokenSeparator']]);

            $container->setDefinition($routerContextServiceName, $routerContextDef);

            //Router
            $routerServiceName = 'gos_pubsub_router.' . $name;

            $routerDef = new Definition($configs['router_class'], [
                new Reference($collectionServiceName),
                new Reference(Matcher::class),
                new Reference(Generator::class),
                new Reference($routeLoaderServiceName),
                $name,
            ]);

            $routerDef->addMethodCall('setContext', [new Reference($routerContextServiceName)]);

            $container->setDefinition($routerServiceName, $routerDef);

            $debugCmdDef->addMethodCall('addRouter', [new Reference($routerServiceName)]);
        }
    }
}
