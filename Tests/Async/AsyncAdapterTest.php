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

namespace Drift\EventBus\Tests\Async;

use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\Event\Event2;
use Drift\EventBus\Tests\Event\Event3;
use Drift\EventBus\Tests\EventBusFunctionalTest;
use Drift\EventBus\Tests\Middleware\Middleware1;
use function Clue\React\Block\await;
use function Clue\React\Block\awaitAll;
use Symfony\Component\Process\Process;

/**
 * Class AsyncAdapterTest.
 */
abstract class AsyncAdapterTest extends EventBusFunctionalTest
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
            ],
            'async_adapter' => static::getAsyncConfiguration(),
        ];

        return $configuration;
    }

    /**
     * Get async configuration.
     *
     * @return array
     */
    abstract protected static function getAsyncConfiguration(): array;

    /**
     * Test temporary consumer.
     */
    public function testTemporaryConsumer()
    {
        @unlink('/tmp/ev1.tmp');
        @unlink('/tmp/ev2.tmp');
        @unlink('/tmp/ev3.tmp');

        $process = $this->consumeEvents();
        usleep(100000);

        $promise1 = $this
            ->getEventBus()
            ->dispatch('event1', new Event1(''));

        $promise2 = $this
            ->getEventBus()
            ->dispatch('event2', new Event2(''));

        $promise3 = $this
            ->getEventBus()
            ->dispatch('event3', new Event3(''));

        awaitAll([
            $promise1,
            $promise2,
            $promise3,
        ], $this->getLoop());

        usleep(100000);
        $output = $process->getOutput();

        $this->assertContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event3", $output);

        $process->stop();

        $promise1 = $this
            ->getEventBus()
            ->dispatch('event1', new Event1(''));

        await($promise1, $this->getLoop());

        usleep(100000);
        $process = $this->consumeEvents();
        usleep(100000);

        $promise2 = $this
            ->getEventBus()
            ->dispatch('event2', new Event2(''));

        await($promise2, $this->getLoop());
        usleep(100000);
        $output = $process->getOutput();

        $this->assertNotContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);
    }

    /**
     * Consume events.
     *
     * @return Process
     */
    protected function consumeEvents(): Process
    {
        return $this->runAsyncCommand([
            'event-bus:consume-events',
        ]);
    }
}
