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

namespace Drift\EventBus\Async;

use Drift\Console\OutputPrinter;
use Drift\EventBus\Bus\Bus;
use Exception;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * Class DummyAdapter.
 */
class InMemoryAdapter extends AsyncAdapter
{
    /**
     * @var array
     */
    private $queue = [];

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'In Memory';
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $eventName, $event): PromiseInterface
    {
        if (!isset($this->queue[$eventName])) {
            $this->queue[$eventName] = [];
        }

        return (new FulfilledPromise())
            ->then(function () use ($eventName, $event) {
                $this->queue[$eventName][] = $event;
            });
    }

    /**
     * Subscribe.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param string        $queueName
     */
    public function subscribe(
        Bus $bus,
        OutputPrinter $outputPrinter,
        string $queueName
    ) {
        throw new Exception('Method not usable');
    }

    /**
     * @return array
     */
    public function getQueue(): array
    {
        return $this->queue;
    }
}
