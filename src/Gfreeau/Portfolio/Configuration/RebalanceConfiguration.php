<?php

namespace Gfreeau\Portfolio\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class RebalanceConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');

        $rootNode
            ->children()
                ->arrayNode('accounts')
                    ->prototype('array')
                        ->children()
                            ->floatNode('contribution')
                                ->defaultValue(0)
                                ->min(0)
                            ->end()
                            ->arrayNode('buyHoldings')
                                ->prototype('integer')
                                    ->min(0)
                                ->end()
                            ->end()
                            ->arrayNode('sellHoldings')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shares')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('symbol')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('assetClass')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($value) { return array($value => 1); })
                                ->end()
                                ->prototype('float')
                                    ->min(0)
                                    ->max(1)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}