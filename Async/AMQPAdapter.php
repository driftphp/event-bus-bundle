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

use Bunny\Channel;
use Bunny\Message;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Drift\Console\OutputPrinter;
use Drift\EventBus\Bus\Bus;
use React\EventLoop\LoopInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * Class AMQPAdapter.
 */
class AMQPAdapter extends AsyncAdapter
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var string
     */
    private $exchangeName;

    /**
     * RedisAdapter constructor.
     *
     * @param Channel       $channel
     * @param LoopInterface $loop
     * @param string        $exchangeName
     */
    public function __construct(
        Channel $channel,
        LoopInterface $loop,
        string $exchangeName
    ) {
        $this->channel = $channel;
        $this->loop = $loop;
        $this->exchangeName = $exchangeName;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'AMQP';
    }

    /**
     * Publish.
     *
     * @param string $eventName
     * @param object $event
     *
     * @return PromiseInterface
     */
    public function publish(
        string $eventName,
        $event
    ): PromiseInterface {
        return $this
            ->channel
            ->publish(serialize($event), [
                'name' => $eventName,
                'delivery_mode' => 2,
            ], $this->exchangeName);
    }

    /**
     * Subscribe.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param string        $queueName
     */
    public function subscribe(
        Bus $bus,
        OutputPrinter $outputPrinter,
        string $queueName
    ) {
        $promise = !empty($queueName)
            ? new FulfilledPromise($queueName)
            : $this
                ->channel
                ->queueDeclare('', false, true, true)
                ->then(function (MethodQueueDeclareOkFrame $okFrame) {
                    $queueName = $okFrame->queue;

                    return $this
                        ->channel
                        ->queueBind($queueName, $this->exchangeName)
                        ->then(function (MethodQueueBindOkFrame $_) use ($queueName) {
                            return $queueName;
                        });
                });

        $promise
            ->then(function (string $queueName) {
                return $this
                    ->channel
                    ->qos(0, 1, true)
                    ->then(function () use ($queueName) {
                        return $queueName;
                    });
            })
            ->then(function (string $queueName) use ($bus, $outputPrinter) {
                return $this
                    ->channel
                    ->consume(function (Message $message, Channel $channel) use ($bus, $outputPrinter) {
                        list($eventName, $event) = $this->unserializeMessage($message);

                        return $this->dispatchEvent(
                            $bus,
                            $eventName,
                            $event,
                            $outputPrinter,
                            function () use ($message) {
                                $this->channel->ack($message);
                            },
                            function () use ($message) {
                                $this->channel->ack($message);
                            }
                        );
                    }, $queueName, '', false, false, true);
            });

        $this
            ->loop
            ->run();
    }

    /**
     * Unserialize event.
     *
     * @var Message
     *
     * @return array
     */
    private function unserializeMessage(Message $message): array
    {
        return [
            $message->getHeader('name'),
            unserialize($message->content),
        ];
    }
}
