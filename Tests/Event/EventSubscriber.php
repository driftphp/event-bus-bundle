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

use Drift\EventBus\Tests\Context;
use Drift\HttpKernel\Event\DomainEventEnvelope;
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
            Event1::class => [
                ['listenEvent1', 0],
            ],
            Event2::class => [
                ['listenEvent2', 0],
            ],
            Event3::class => [
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
            ->values[Event1::class] = $event->getValue();

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
            ->values[Event2::class] = $event->getValue();

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
            ->values[Event3::class] = $symfonyEvent->getValue();

        touch('/tmp/ev3.tmp');
    }
}
