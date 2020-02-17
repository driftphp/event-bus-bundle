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

namespace Drift\EventBus\Tests\Router;

use Drift\EventBus\Exception\EmptyExchangeListException;
use Drift\EventBus\Exception\InvalidExchangeException;
use Drift\EventBus\Router\Router;
use Drift\EventBus\Tests\Event\Event1;
use Drift\EventBus\Tests\Event\Event2;
use Drift\EventBus\Tests\Event\Event3;
use Drift\HttpKernel\Event\DomainEventEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * Class RouterTest.
 */
class RouterTest extends TestCase
{
    /**
     * Test empty.
     */
    public function testEmpty()
    {
        $this->expectException(EmptyExchangeListException::class);
        new Router([], []);
    }

    /**
     * Test with router.
     */
    public function testWithRouter()
    {
        $router = new Router([
            Event1::class => 'exchange1',
        ], [
            'exchange1' => 'real_exchange',
        ]);
        $this->assertEquals('real_exchange', $router->getExchangeByAlias('exchange1'));
        $this->assertEquals('real_exchange', $router->getExchangeByEvent(new Event1('')));
        $this->assertEquals('real_exchange', $router->getExchangeByEvent(new Event2('')));
        $this->assertEquals('real_exchange', $router->getExchangeByEvent(new DomainEventEnvelope(
            new Event2('')
        )));
    }

    /**
     * Test with router.
     */
    public function testMissingExchangeOnAlias()
    {
        $router = new Router([], [
            'exchange1' => 'real_exchange',
        ]);

        $this->expectException(InvalidExchangeException::class);
        $router->getExchangeByAlias('exchange2');
    }

    /**
     * Test with router.
     */
    public function testMissingExchangeOnEvent()
    {
        $router = new Router([
            Event3::class => 'exchange2',
        ], [
            'exchange1' => 'real_exchange',
        ]);

        $this->expectException(InvalidExchangeException::class);
        $router->getExchangeByEvent(new Event3(''));
    }
}
