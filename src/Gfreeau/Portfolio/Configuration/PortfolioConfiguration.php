<?php

namespace Gfreeau\Portfolio\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class PortfolioConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('config');

        $rootNode
            ->children()
                ->floatNode('tradingFee')
                    ->defaultValue(0)
                    ->min(0)
                ->end()
                ->arrayNode('assetClasses')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('float')
                        ->min(0)
                        ->max(1)
                    ->end()
                ->end()
                ->arrayNode('accounts')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->floatNode('cash')
                                ->min(0)
                            ->end()
                            ->arrayNode('holdings')
                                ->prototype('integer')
                                    ->min(0)
                                ->end()
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