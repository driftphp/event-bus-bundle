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

namespace Drift\EventBus\Tests\Bus;

use function Clue\React\Block\await;
use Drift\EventBus\Bus\Bus;
use Drift\EventBus\Middleware\AsyncMiddleware;
use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\EventBusFunctionalTest;
use Drift\EventBus\Tests\Middleware\Middleware1;
use Drift\EventBus\Tests\Middleware\Middleware2;

/**
 * Class AsyncEventBusSimpleTest.
 */
class AsyncEventBusSimpleTest extends EventBusFunctionalTest
{
    /**
     * Decorate configuration.
     *
     * @param array $configuration
     *
     * @return array
     */
    protected static function decorateConfiguration(array $configuration): array
    {
        $configuration['imports'] = [
            ['resource' => __DIR__.'/../autowiring.yml'],
        ];

        $configuration['event_bus'] = [
            'middlewares' => [
                Middleware1::class.'::anotherMethod',
                Middleware2::class,
            ],
            'exchanges' => [
                'default' => 'events1',
            ],
            'async_pass_through' => true,
            'async_adapter' => [
                'adapter' => 'in_memory',
            ],
        ];

        return $configuration;
    }

    /**
     * Test event bus.
     */
    public function testEventBus()
    {
        $this->resetContext();
        $promise = $this
            ->getEventBus()
            ->dispatch(new Event1('thing'));

        await($promise, $this->getLoop());

        $this->assertEquals([
            [
                'class' => AsyncMiddleware::class,
                'method' => 'dispatch',
            ],
            [
                'class' => Middleware1::class,
                'method' => 'anotherMethod',
            ],
            [
                'class' => Middleware2::class,
                'method' => 'dispatch',
            ],
        ], $this->get('drift.event_bus.test')->getMiddlewareList());
        $this->assertEquals(['2', '1'], $this->getContextValue('middleware'));
    }

    /**
     * Test inline event bus.
     */
    public function testInlineEventBus()
    {
        $this->resetContext();
        $promise = $this
            ->getInlineEventBus()
            ->dispatch(new Event1('thing'));

        await($promise, $this->getLoop());

        $this->assertEquals([
            [
                'class' => Middleware1::class,
                'method' => 'anotherMethod',
            ],
            [
                'class' => Middleware2::class,
                'method' => 'dispatch',
            ],
        ], $this->get('drift.inline_event_bus.test')->getMiddlewareList());
        $this->assertEquals(['2', '1'], $this->getContextValue('middleware'));
    }
}
