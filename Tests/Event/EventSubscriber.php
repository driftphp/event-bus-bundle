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

namespace Drift\EventBus\Tests\Event;

use Drift\EventBus\Event\DomainEventEnvelope;
use Drift\EventBus\Tests\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EventListener.
 */
final class EventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * EventSubscriber constructor.
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'event1' => [
                ['listenEvent1', 0],
            ],
            'event2' => [
                ['listenEvent2', 0],
            ],
            'event3' => [
                ['listenEvent3', 0],
            ],
        ];
    }

    /**
     * Listen event.
     *
     * @param DomainEventEnvelope $domainEventEnvelope
     */
    public function listenEvent1(DomainEventEnvelope $domainEventEnvelope)
    {
        $event = $domainEventEnvelope->getDomainEvent();
        $this
            ->context
            ->values[$domainEventEnvelope->getEventName()] = $event->getValue();

        touch('/tmp/ev1.tmp');
    }

    /**
     * Listen event.
     *
     * @param DomainEventEnvelope $domainEventEnvelope
     */
    public function listenEvent2(DomainEventEnvelope $domainEventEnvelope)
    {
        $event = $domainEventEnvelope->getDomainEvent();
        $this
            ->context
            ->values[$domainEventEnvelope->getEventName()] = $event->getValue();

        touch('/tmp/ev2.tmp');
    }

    /**
     * Listen event.
     *
     * @param Event3 $symfonyEvent
     */
    public function listenEvent3(Event3 $symfonyEvent)
    {
        $this
            ->context
            ->values['event3'] = $symfonyEvent->getValue();

        touch('/tmp/ev3.tmp');
    }
}
