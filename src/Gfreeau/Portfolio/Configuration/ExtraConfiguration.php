<?php

namespace Gfreeau\Portfolio\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ExtraConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');

        $rootNode
            ->children()
                ->arrayNode('prices')
                    ->prototype('float')
                        ->min(0)
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}