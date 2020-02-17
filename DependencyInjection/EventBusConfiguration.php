<?php

/*
 * This file is part of the DriftPHP Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Drift\EventBus\DependencyInjection;

use Drift\EventBus\Bus\Bus;
use Mmoreram\BaseBundle\DependencyInjection\BaseConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Class EventBusConfiguration.
 */
class EventBusConfiguration extends BaseConfiguration
{
    /**
     * Configure the root node.
     *
     * @param ArrayNodeDefinition $rootNode Root node
     */
    protected function setupTree(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('middlewares')
                    ->scalarPrototype()
                        ->defaultValue([])
                    ->end()
                ->end()
                ->enumNode('distribution')
                    ->values([Bus::DISTRIBUTION_INLINE, Bus::DISTRIBUTION_NEXT_TICK])
                    ->defaultValue(Bus::DISTRIBUTION_INLINE)
                ->end()
                ->arrayNode('exchanges')
                    ->scalarPrototype()
                        ->isRequired()
                    ->end()
                ->end()
                ->arrayNode('router')
                    ->scalarPrototype()
                        ->defaultValue([])
                    ->end()
                ->end()
                ->arrayNode('async_adapter')
                    ->children()
                        ->enumNode('adapter')
                            ->values(['amqp', 'in_memory'])
                        ->end()
                        ->booleanNode('pass_through')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('amqp')
                            ->children()
                                ->scalarNode('client')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
