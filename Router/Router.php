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

namespace Drift\EventBus\Router;

use Drift\EventBus\Exception\EmptyExchangeListException;
use Drift\EventBus\Exception\InvalidExchangeException;
use Drift\HttpKernel\Event\DomainEventEnvelope;

/**
 * Class Router.
 */
final class Router
{
    /**
     * @var string[]
     */
    private $routes;

    /**
     * @var string[]
     */
    private $exchanges;

    /**
     * Router constructor.
     *
     * @param string[] $routes
     * @param string[] $exchanges
     *
     * @throws EmptyExchangeListException
     */
    public function __construct(
        array $routes,
        array $exchanges
    ) {
        if (empty($exchanges)) {
            throw new EmptyExchangeListException('Empty exchange list. You should configure, at least, one');
        }

        $this->routes = $routes;
        $this->exchanges = $exchanges;
    }

    /**
     * Get exchanges name by Event.
     *
     * @param object Event
     *
     * @return string[]
     *
     * @throws InvalidExchangeException
     */
    public function getExchangesByEvent($event): array
    {
        $event = $event instanceof DomainEventEnvelope
            ? $event->getDomainEvent()
            : $event;

        $eventParts = explode('\\', get_class($event));
        $eventLastPart = end($eventParts);
        $routes = $this->routes;

        $exchangeAlias =
            $routes['_all'] ??
            $routes[get_class($event)] ??
            $routes[$eventLastPart] ??
            $routes['_others'] ??
            null;

        if (is_null($exchangeAlias)) {
            return [];
        }

        $exchangeAliases = explode(',', $exchangeAlias);

        return array_map(function (string $exchangeAlias) {
            return $this->getExchangeByAlias(trim($exchangeAlias));
        }, $exchangeAliases);
    }

    /**
     * Get exchange by alias.
     *
     * @param string $alias
     *
     * @return string
     *
     * @throws InvalidExchangeException
     */
    public function getExchangeByAlias(string $alias): string
    {
        if (!isset($this->exchanges[$alias])) {
            throw new InvalidExchangeException(sprintf('Exchange with name %s is not configured', $alias));
        }

        return $this->exchanges[$alias];
    }
}
