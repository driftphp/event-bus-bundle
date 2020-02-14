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

namespace Drift\EventBus\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class DomainEventEnvelope.
 */
final class DomainEventEnvelope extends Event
{
    /**
     * @var string
     */
    private $eventName;

    /**
     * @var object
     */
    private $domainEvent;

    /**
     * DomainEventEnvelope constructor.
     *
     * @param string $eventName
     * @param object $domainEvent
     */
    public function __construct(
        string $eventName,
        object $domainEvent
    ) {
        $this->eventName = $eventName;
        $this->domainEvent = $domainEvent;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * Get domain event.
     *
     * @return object
     */
    public function getDomainEvent(): object
    {
        return $this->domainEvent;
    }
}
