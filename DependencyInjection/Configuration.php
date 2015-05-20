<?php

namespace Fenrizbes\IpGeoBaseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('fenrizbes_ip_geo_base');

        $rootNode->children()
            ->integerNode('default_city')
                ->defaultNull()
            ->end()

            ->booleanNode('enabled')
                ->defaultTrue()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
