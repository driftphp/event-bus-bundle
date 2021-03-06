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
use Drift\EventBus\Middleware\AsyncMiddleware;
use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\EventBusFunctionalTest;

/**
 * Class AsyncEventBusTest.
 */
class AsyncEventBusTest extends EventBusFunctionalTest
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
            'exchanges' => [
                'default' => 'events1',
            ],
            'async_pass_through' => false,
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
        $promise = $this
            ->getEventBus()
            ->dispatch(new Event1('thing'));

        await($promise, $this->getLoop());

        $this->assertNull($this->getContextValue(Event1::class));

        $this->assertEquals([
            [
                'class' => AsyncMiddleware::class,
                'method' => 'dispatch',
            ],
        ], $this->get('drift.event_bus.test')->getMiddlewareList());
        $this->assertEquals(null, $this->getContextValue('middleware'));
    }

    /**
     * Test inline event bus.
     */
    public function testInlineEventBus()
    {
        $promise = $this
            ->getInlineEventBus()
            ->dispatch(new Event1('thing'));

        await($promise, $this->getLoop());

        $this->assertNotNull($this->getContextValue(Event1::class));

        $this->assertEquals([], $this->get('drift.inline_event_bus.test')->getMiddlewareList());
        $this->assertEquals(null, $this->getContextValue('middleware'));
    }
}
