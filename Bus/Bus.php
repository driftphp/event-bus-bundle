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

namespace Drift\EventBus\Bus;

use Drift\EventBus\Exception\InvalidEventException;
use Drift\EventBus\Middleware\DebugableMiddleware;
use Drift\HttpKernel\AsyncEventDispatcherInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class Bus.
 */
class Bus
{
    /**
     * @var string
     */
    const DISTRIBUTION_INLINE = 'inline';

    /**
     * @var string
     */
    const DISTRIBUTION_NEXT_TICK = 'next_tick';

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var callable
     */
    private $middlewareChain;

    /**
     * @var array
     */
    private $middleware;

    /**
     * @var AsyncEventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var bool
     */
    private $dispatchEvent;

    /**
     * @param LoopInterface                 $loop
     * @param AsyncEventDispatcherInterface $eventDispatcher
     * @param array                         $middleware
     * @param string                        $distribution
     * @param bool                          $dispatchEvent
     */
    public function __construct(
        LoopInterface $loop,
        AsyncEventDispatcherInterface $eventDispatcher,
        array $middleware,
        string $distribution,
        bool $dispatchEvent
    ) {
        $this->loop = $loop;
        $this->eventDispatcher = $eventDispatcher;
        $this->dispatchEvent = $dispatchEvent;
        $this->middleware = array_map(function (DebugableMiddleware $middleware) {
            return $middleware->getMiddlewareInfo();
        }, $middleware);

        $this->middlewareChain = self::DISTRIBUTION_NEXT_TICK === $distribution
            ? $this->createNextTickExecutionChain($middleware)
            : $this->createInlineExecutionChain($middleware);
    }

    /**
     * Dispatch the event.
     *
     * @param object $event
     *
     * @return mixed
     *
     * @throws InvalidEventException
     */
    public function dispatch($event)
    {
        if (!is_object($event)) {
            throw new InvalidEventException();
        }

        $promise = (($this->middlewareChain)($event));

        if ($this->dispatchEvent) {
            $promise = $promise->then(function () use ($event) {
                return $this
                    ->eventDispatcher
                    ->asyncDispatch($event);
            });
        }

        return $promise->then(function () {
            return;
        });
    }

    /**
     * Create execution chain.
     *
     * @param array $middlewareList
     *
     * @return callable
     */
    private function createInlineExecutionChain($middlewareList)
    {
        $lastCallable = function () {
            return new FulfilledPromise();
        };

        while ($middleware = array_pop($middlewareList)) {
            $lastCallable = function ($event) use ($middleware, $lastCallable) {
                return $middleware->dispatch($event, $lastCallable);
            };
        }

        return $lastCallable;
    }

    /**
     * Create next tick execution chain.
     *
     * @param array $middlewareList
     *
     * @return callable
     */
    private function createNextTickExecutionChain($middlewareList)
    {
        $lastCallable = function () {};

        while ($middleware = array_pop($middlewareList)) {
            $lastCallable = function ($event) use ($middleware, $lastCallable) {
                $deferred = new Deferred();
                $this
                    ->loop
                    ->futureTick(function () use ($deferred, $middleware, $event, $lastCallable) {
                        $deferred->resolve($middleware->dispatch(
                            $event,
                            $lastCallable
                        ));
                    });

                return $deferred->promise();
            };
        }

        return $lastCallable;
    }

    /**
     * Get middleware list.
     *
     * @return array
     */
    public function getMiddlewareList(): array
    {
        return $this->middleware;
    }
}
