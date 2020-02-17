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

namespace Drift\EventBus\Middleware;

use Drift\EventBus\Async\AsyncAdapter;
use React\Promise\PromiseInterface;

/**
 * Class AsyncMiddleware.
 */
class AsyncMiddleware implements DebugableMiddleware
{
    /**
     * @var AsyncAdapter
     */
    private $asyncAdapter;

    /**
     * @var bool
     */
    private $passThrough;

    /**
     * AsyncMiddleware constructor.
     *
     * @param AsyncAdapter $asyncAdapter
     * @param bool         $passThrough
     */
    public function __construct(
        AsyncAdapter $asyncAdapter,
        bool $passThrough
    ) {
        $this->asyncAdapter = $asyncAdapter;
        $this->passThrough = $passThrough;
    }

    /**
     * Handle.
     *
     * @param string $eventName
     * @parma Object $event
     *
     * @param callable $next
     *
     * @return PromiseInterface
     */
    public function dispatch(
        $event,
        callable $next
    ): PromiseInterface {
        $promise = $this
            ->asyncAdapter
            ->publish($event);

        if ($this->passThrough) {
            $promise = $promise->then(function () use ($next, $event) {
                return $next($event);
            });
        }

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewareInfo(): array
    {
        return [
            'class' => self::class,
            'method' => 'dispatch',
        ];
    }
}
