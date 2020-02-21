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
use Drift\EventBus\Tests\Event\Event4;
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
            'router' => [
                Event1::class => 'events_internal1',
                Event2::class => 'events_internal1, events_internal2',
                Event3::class => 'events_internal2',
            ],
            'exchanges' => [
                'events_internal1' => 'events1',
                'events_internal2' => 'events2',
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
     * Test infrastructure.
     */
    public function testInfrastructure()
    {
        $output = $this->dropInfrastructure(['events_internal1:queue1', 'events_internal2:queue2']);
        $this->assertContains('events_internal1 deleted properly', $output);
        $this->assertContains('events_internal2 deleted properly', $output);
        $this->assertContains('events_internal2 deleted properly', $output);

        $output = $this->createInfrastructure(['events_internal1:queue1', 'events_internal2']);
        $this->assertContains('events_internal1 created properly', $output);
        $this->assertContains('queue1 created properly', $output);
        $this->assertContains('events_internal2 created properly', $output);

        $output = $this->checkInfrastructure(['events_internal1:queue1', 'events_internal2']);
        $this->assertContains('events_internal1 exists', $output);
        $this->assertContains('events_internal2 exists', $output);
        $this->assertContains('queue1 exists', $output);

        $output = $this->checkInfrastructure(['events_internal1:queue2']);
        $this->assertContains('queue2 does not exist', $output);

        $output = $this->checkInfrastructure(['events_internal3']);
        $this->assertContains('events_internal3 is not configured', $output);

        $output = $this->dropInfrastructure(['events_internal1:queue1', 'events_internal2:queue4']);
        $this->assertContains('events_internal1 deleted properly', $output);
        $this->assertContains('events_internal2 deleted properly', $output);
        $this->assertContains('events_internal2 deleted properly', $output);
    }

    /**
     * Test temporary consumer.
     */
    public function testTemporaryConsumer()
    {
        @unlink('/tmp/ev1.tmp');
        @unlink('/tmp/ev2.tmp');
        @unlink('/tmp/ev3.tmp');

        $this->dropInfrastructure(['events_internal1:queue1', 'events_internal2']);
        $this->createInfrastructure(['events_internal1:queue1', 'events_internal2:queue2']);

        $process = $this->consumeEvents(['events_internal1', 'events_internal2']);
        usleep(500000);

        $promise1 = $this
            ->getEventBus()
            ->dispatch(new Event1(''));

        $promise2 = $this
            ->getEventBus()
            ->dispatch(new Event2(''));

        $promise3 = $this
            ->getEventBus()
            ->dispatch(new Event3(''));

        awaitAll([
            $promise1,
            $promise2,
            $promise3,
        ], $this->getLoop());

        usleep(500000);
        $output = $process->getOutput();

        $this->assertContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event3", $output);

        $process->stop();

        $promise1 = $this
            ->getEventBus()
            ->dispatch(new Event1(''));

        await($promise1, $this->getLoop());

        usleep(500000);
        $process = $this->consumeEvents(['events_internal1', 'events_internal2']);
        usleep(500000);

        $promise2 = $this
            ->getEventBus()
            ->dispatch(new Event2(''));

        $promise4 = $this
            ->getEventBus()
            ->dispatch(new Event4());

        awaitAll([
            $promise2,
            $promise4,
        ], $this->getLoop());
        usleep(500000);
        $output = $process->getOutput();

        $this->assertNotContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);

        $process = $this->consumeEvents(['events_internal1:queue1']);
        usleep(500000);
        $output = $process->getOutput();
        $this->assertContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);
        $this->assertNotContains("\033[01;32mConsumed\033[0m Event3", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event4", $output);
        $process->stop();

        $process = $this->consumeEvents(['events_internal2:queue2']);
        usleep(500000);
        $output = $process->getOutput();
        $this->assertNotContains("\033[01;32mConsumed\033[0m Event1", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event2", $output);
        $this->assertContains("\033[01;32mConsumed\033[0m Event3", $output);
        $this->assertNotContains("\033[01;32mConsumed\033[0m Event4", $output);

        $this->dropInfrastructure(['events_internal1:queue1', 'events_internal2:queue2']);
    }

    /**
     * Consume events.
     *
     * @param array $exchanges
     *
     * @return Process
     */
    protected function consumeEvents(array $exchanges): Process
    {
        $array = ['event-bus:consume-events'];
        foreach ($exchanges as $exchange) {
            $array[] = "--exchange=$exchange";
        }

        return $this->runAsyncCommand($array);
    }

    /**
     * Drop infrastructure.
     *
     * @param array $exchanges
     *
     * @return string
     */
    private function dropInfrastructure(array $exchanges): string
    {
        $array = ['event-bus:infra:drop', '--force'];
        foreach ($exchanges as $exchange) {
            $array[] = "--exchange=$exchange";
        }

        $process = $this->runAsyncCommand($array);
        usleep(600000);

        return $process->getOutput();
    }

    /**
     * Create infrastructure.
     *
     * @param array $exchanges
     *
     * @return string
     */
    private function createInfrastructure(array $exchanges): string
    {
        $array = ['event-bus:infra:create', '--force'];
        foreach ($exchanges as $exchange) {
            $array[] = "--exchange=$exchange";
        }

        $process = $this->runAsyncCommand($array);
        usleep(500000);

        return $process->getOutput();
    }

    /**
     * Check infrastructure.
     *
     * @param array $exchanges
     *
     * @return string
     */
    private function checkInfrastructure(array $exchanges): string
    {
        $array = ['event-bus:infra:check'];
        foreach ($exchanges as $exchange) {
            $array[] = "--exchange=$exchange";
        }

        $process = $this->runAsyncCommand($array);
        usleep(500000);

        return $process->getOutput();
    }
}
