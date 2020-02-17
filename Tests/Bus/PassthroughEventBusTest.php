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

use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\EventBusFunctionalTest;
use function Clue\React\Block\await;

/**
 * Class PassthroughEventBusTest.
 */
class PassthroughEventBusTest extends EventBusFunctionalTest
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
            'async_adapter' => [
                'adapter' => 'in_memory',
                'pass_through' => true,
            ],
        ];

        return $configuration;
    }

    /**
     * Test buses are being built.
     */
    public function testQueryBus()
    {
        $promise = $this
            ->getEventBus()
            ->dispatch(new Event1('thing'));

        await($promise, $this->getLoop());

        $this->assertEquals('thing', $this->getContextValue(Event1::class));
    }
}
