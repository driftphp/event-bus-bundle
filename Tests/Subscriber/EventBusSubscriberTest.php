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

use Drift\EventBus\Subscriber\EventBusSubscriber;
use Drift\EventBus\Tests\EventBusFunctionalTest;

/**
 * Class EventBusSubscriberTest.
 */
class EventBusSubscriberTest extends EventBusFunctionalTest
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
     * Test service is public.
     */
    public function testServiceIsPublic()
    {
        $this->expectNotToPerformAssertions();
        $this->get(EventBusSubscriber::class);
    }
}
