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
use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\EventBusFunctionalTest;

/**
 * Class EventBusSimpleNoMiddlewareTest.
 */
class EventBusSimpleNoMiddlewareTest extends EventBusFunctionalTest
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
            ['resource' => __DIR__.'/../autowiring-no-middleware.yml'],
        ];

        $configuration['event_bus'] = [
            'exchanges' => [
                'default' => 'events1',
            ],
            'distribution' => static::distributedBus()
                ? Bus::DISTRIBUTION_NEXT_TICK
                : Bus::DISTRIBUTION_INLINE,
        ];

        return $configuration;
    }

    /**
     * Create distributed bus.
     *
     * @return bool
     */
    protected static function distributedBus(): bool
    {
        return false;
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

        $this->assertEquals('thing', $this->getContextValue(Event1::class));
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

        $this->assertEquals('thing', $this->getContextValue(Event1::class));
    }
}
