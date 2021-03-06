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
 * Class Middleware1.
 */
class Middleware1
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
     * @param object   $event
     * @param callable $next
     *
     * @return mixed
     */
    public function anotherMethod($event, callable $next)
    {
        return $next($event)
            ->then(function () {
                if (!isset($this->context->values['middleware'])) {
                    $this->context->values['middleware'] = [];
                }

                return $this->context->values['middleware'][] = '1';
            });
    }
}
