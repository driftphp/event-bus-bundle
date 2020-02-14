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

namespace Drift\EventBus\Tests;

use Drift\EventBus\Bus\EventBus;
use Drift\EventBus\Bus\InlineEventBus;
use Drift\EventBus\EventBusBundle;
use Mmoreram\BaseBundle\Kernel\DriftBaseKernel;
use Mmoreram\BaseBundle\Tests\BaseFunctionalTest;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class EventBusFunctionalTest.
 */
abstract class EventBusFunctionalTest extends BaseFunctionalTest
{
    /**
     * @var BufferedOutput
     */
    private static $output;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public static function setUpBeforeClass()
    {
        self::$output = new BufferedOutput();
        parent::setUpBeforeClass();
    }

    /**
     * Get kernel.
     *
     * @return KernelInterface
     */
    protected static function getKernel(): KernelInterface
    {
        $configuration = [
            'parameters' => [
                'kernel.secret' => 'gdfgfdgd',
            ],
            'framework' => [
                'test' => true,
            ],
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => false,
                    'public' => true,
                ],

                Context::class => null,
                'reactphp.event_loop' => [
                    'class' => LoopInterface::class,
                    'public' => true,
                    'factory' => [
                        Factory::class,
                        'create',
                    ],
                ],

                'drift.event_bus.test' => [
                    'alias' => 'drift.event_bus',
                ],

                'drift.inline_event_bus.test' => [
                    'alias' => 'drift.inline_event_bus',
                ],
            ],
        ];

        return new DriftBaseKernel(
            static::decorateBundles([
                FrameworkBundle::class,
                EventBusBundle::class,
            ]),
            static::decorateConfiguration($configuration),
            [],
            static::environment(), static::debug()
        );
    }

    /**
     * Decorate bundles.
     *
     * @param array $bundles
     *
     * @return array
     */
    protected static function decorateBundles(array $bundles): array
    {
        return $bundles;
    }

    /**
     * Decorate configuration.
     *
     * @param array $configuration
     *
     * @return array
     */
    protected static function decorateConfiguration(array $configuration): array
    {
        return $configuration;
    }

    /**
     * Kernel in debug mode.
     *
     * @return bool
     */
    protected static function debug(): bool
    {
        return false;
    }

    /**
     * Kernel in debug mode.
     *
     * @return string
     */
    protected static function environment(): string
    {
        return 'dev';
    }

    /**
     * Get event bus.
     *
     * @return EventBus
     */
    protected function getEventBus(): EventBus
    {
        return $this->get('drift.event_bus.test');
    }

    /**
     * Get event bus.
     *
     * @return InlineEventBus
     */
    protected function getInlineEventBus(): InlineEventBus
    {
        return $this->get('drift.inline_event_bus.test');
    }

    /**
     * Get loop.
     *
     * @return LoopInterface
     */
    protected function getLoop(): LoopInterface
    {
        return $this->get('reactphp.event_loop');
    }

    /**
     * Get context value.
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function getContextValue(string $value)
    {
        return $this->get(Context::class)->values[$value] ?? null;
    }

    /**
     * Reset context.
     *
     * @return mixed
     */
    protected function resetContext()
    {
        return $this->get(Context::class)->values = [];
    }
}
