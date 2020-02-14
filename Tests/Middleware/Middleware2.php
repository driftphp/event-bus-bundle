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

namespace Drift\EventBus\Tests\Middleware;

use Drift\EventBus\Tests\Context;

/**
 * Class Middleware2.
 */
class Middleware2
{
    /**
     * @var Context
     */
    private $context;

    /**
     * Middleware1 constructor.
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param string   $eventName
     * @param object   $event
     * @param callable $next
     *
     * @return mixed
     */
    public function dispatch(string $eventName, $event, callable $next)
    {
        return $next($eventName, $event)
            ->then(function () {
                if (!isset($this->context->values['middleware'])) {
                    $this->context->values['middleware'] = [];
                }

                return $this->context->values['middleware'][] = '2';
            });
    }
}
