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
use Drift\Console\TimeFormatter;
use Drift\EventBus\Bus\Bus;
use Drift\EventBus\Console\EventConsumedLineMessage;
use Drift\EventBus\Exception\InvalidExchangeException;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * Interface AsyncAdapter.
 */
abstract class AsyncAdapter
{
    /**
     * Get adapter name.
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Create infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    abstract public function createInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface;

    /**
     * Drop infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    abstract public function dropInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface;

    /**
     * Check infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    abstract public function checkInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface;

    /**
     * Publish.
     *
     * @param object $event
     *
     * @return PromiseInterface
     */
    abstract public function publish($event): PromiseInterface;

    /**
     * Subscribe.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param array         $exchanges
     *
     * @throws InvalidExchangeException
     */
    abstract public function subscribe(
        Bus $bus,
        OutputPrinter $outputPrinter,
        array $exchanges
    );

    /**
     * dispatch event.
     *
     * @param Bus $bus
     * @param string InlineEventBus
     * @param object        $event
     * @param OutputPrinter $outputPrinter
     * @param callable      $ok
     * @param callable      $ko
     *
     * @return PromiseInterface
     */
    protected function dispatchEvent(
        Bus $bus,
        $event,
        OutputPrinter $outputPrinter,

        callable $ok,
        callable $ko
    ): PromiseInterface {
        $from = microtime(true);

        return $bus
            ->dispatch($event)
            ->then(function () use ($from, $outputPrinter, $event, $ok) {
                $to = microtime(true);

                (new EventConsumedLineMessage(
                    $event,
                    TimeFormatter::formatTime($to - $from),
                    EventConsumedLineMessage::CONSUMED
                ))->print($outputPrinter);

                return (new FulfilledPromise())
                    ->then(function () use ($ok) {
                        return $ok();
                    });
            })
            ->otherwise(function (\Exception $exception) use ($from, $outputPrinter, $event, $ok, $ko) {
                $to = microtime(true);

                (new EventConsumedLineMessage(
                    $event,
                    TimeFormatter::formatTime($to - $from),
                    EventConsumedLineMessage::REJECTED
                ))->print($outputPrinter);

                return (new FulfilledPromise())
                    ->then(function () use ($ko) {
                        return $ko();
                    });
            });
    }
}
