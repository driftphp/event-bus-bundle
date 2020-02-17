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
use Bunny\Exception\ClientException;
use Bunny\Message;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Drift\Console\OutputPrinter;
use Drift\EventBus\Bus\Bus;
use Drift\EventBus\Console\EventBusHeaderMessage;
use Drift\EventBus\Console\EventBusLineMessage;
use Drift\EventBus\Exception\InvalidExchangeException;
use Drift\EventBus\Router\Router;
use function React\Promise\all;
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
     * @var Router
     */
    private $router;

    /**
     * RedisAdapter constructor.
     *
     * @param Channel       $channel
     * @param LoopInterface $loop
     * @param Router        $router
     */
    public function __construct(
        Channel $channel,
        LoopInterface $loop,
        Router $router
    ) {
        $this->channel = $channel;
        $this->loop = $loop;
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'AMQP';
    }

    /**
     * Create infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function createInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface {
        $promises = [];
        foreach ($exchanges as $exchangeAlias => $queue) {
            $exchange = $this
                ->router
                ->getExchangeByAlias($exchangeAlias);

            $promises[] = $this
                ->channel
                ->exchangeDeclare($exchange, 'fanout', false, true)
                ->then(function (MethodExchangeDeclareOkFrame $_) use ($exchangeAlias, $exchange, $queue, $outputPrinter) {
                    if (empty($queue)) {
                        return;
                    }

                    return $this
                        ->channel
                        ->queueDeclare($queue, false, true)
                        ->then(function (MethodQueueDeclareOkFrame $okFrame) use ($queue, $exchange, $exchangeAlias, $outputPrinter) {
                            (new EventBusLineMessage(sprintf('Queue with name %s created properly', $queue)))->print($outputPrinter);

                            return $this
                                ->channel
                                ->queueBind($queue, $exchange)
                                ->then(function () use ($queue, $exchangeAlias, $outputPrinter) {
                                    (new EventBusLineMessage(sprintf(
                                        'Queue with name %s binded properly to exchange with name %s',
                                        $queue,
                                        $exchangeAlias
                                    )))->print($outputPrinter);
                                })
                                ->otherwise(function (\Throwable $throwable) use ($queue, $exchangeAlias, $outputPrinter) {
                                    (new EventBusLineMessage(sprintf(
                                        'Queue with name %s could not be binded to exchange with name %s. Reason - %s',
                                        $queue,
                                        $exchangeAlias,
                                        $throwable->getMessage()
                                    )))->print($outputPrinter);
                                });
                        })
                        ->otherwise(function (\Throwable $throwable) use ($queue, $outputPrinter) {
                            (new EventBusLineMessage(sprintf(
                                'Exchange with name %s could not be created. Reason - %s',
                                $queue,
                                $throwable->getMessage()
                            )))->print($outputPrinter);
                        });
                })
                ->then(function () use ($exchangeAlias, $queue, $outputPrinter) {
                    (new EventBusLineMessage(sprintf('Exchange with name %s created properly', $exchangeAlias)))->print($outputPrinter);
                })
                ->otherwise(function (\Throwable $throwable) use ($exchangeAlias, $queue, $outputPrinter) {
                    (new EventBusLineMessage(sprintf(
                        'Exchange with name %s could not be created. Reason - %s',
                        $exchangeAlias,
                        $throwable->getMessage()
                    )))->print($outputPrinter);
                });
        }

        return all($promises);
    }

    /**
     * Drop infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function dropInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface {
        $promises = [];
        foreach ($exchanges as $exchangeAlias => $queue) {
            $exchange = $this
                ->router
                ->getExchangeByAlias($exchangeAlias);

            $promises[] = $this
                ->channel
                ->exchangeDelete($exchange, false)
                ->then(function () use ($exchangeAlias, $queue, $outputPrinter) {
                    (new EventBusLineMessage(sprintf('Exchange with name %s deleted properly', $exchangeAlias)))->print($outputPrinter);

                    return $this
                        ->channel
                        ->queueDelete($queue, false, false)
                        ->then(function () use ($queue, $outputPrinter) {
                            (new EventBusLineMessage(sprintf('Queue with name %s deleted properly', $queue)))->print($outputPrinter);
                        })
                        ->otherwise(function (\Throwable $throwable) use ($queue, $outputPrinter) {
                            (new EventBusLineMessage(sprintf(
                                'Queue with name %s was impossible to be deleted. Reason - %s',
                                $queue,
                                $throwable->getMessage()
                            )))->print($outputPrinter);
                        });
                })
                ->otherwise(function (ClientException $exception) use ($exchangeAlias, $outputPrinter) {
                    (new EventBusLineMessage(sprintf(
                        'Exchange with name %s was impossible to be deleted. Reason - %s',
                        $exchangeInternal,
                        $exception->getMessage()
                    )))->print($outputPrinter);
                });
        }

        return all($promises);
    }

    /**
     * Check infrastructure.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function checkInfrastructure(
        array $exchanges,
        OutputPrinter $outputPrinter
    ): PromiseInterface {
        $promises = [];
        foreach ($exchanges as $exchangeAlias => $queue) {
            $exchange = $this
                ->router
                ->getExchangeByAlias($exchangeAlias);

            $promises[] = $this
                ->channel
                ->exchangeDeclare($exchange, 'fanout', true)
                ->then(function ($_) use ($exchangeAlias, $queue, $outputPrinter) {
                    (new EventBusLineMessage(sprintf('Exchange with name %s exists', $exchangeAlias)))->print($outputPrinter);

                    return $this
                        ->channel
                        ->queueDeclare($queue, true, true, true)
                        ->then(function ($_) use ($queue, $outputPrinter) {
                            (new EventBusLineMessage(sprintf('Queue with name %s exists', $queue)))->print($outputPrinter);
                        })
                        ->otherwise(function (ClientException $exception) use ($queue, $outputPrinter) {
                            (new EventBusLineMessage(sprintf(
                                'Queue with name %s does not exist. Reason - %s',
                                $queue,
                                $exception->getMessage()
                            )))->print($outputPrinter);
                        });
                })
                ->otherwise(function (ClientException $exception) use ($exchangeAlias, $outputPrinter) {
                    (new EventBusLineMessage(sprintf(
                        'Exchange with name %s does not exist. Reason - %s',
                        $exchangeAlias,
                        $exception->getMessage()
                    )))->print($outputPrinter);
                });
        }

        return all($promises);
    }

    /**
     * Publish.
     *
     * @param object $event
     *
     * @return PromiseInterface
     */
    public function publish($event): PromiseInterface
    {
        return $this
            ->channel
            ->publish(serialize($event), [
                'delivery_mode' => 2,
            ], $this
                ->router
                ->getExchangeByEvent($event)
            );
    }

    /**
     * Subscribe.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param array         $exchanges
     *
     * @throws InvalidExchangeException
     */
    public function subscribe(
        Bus $bus,
        OutputPrinter $outputPrinter,
        array $exchanges
    ) {
        foreach ($exchanges as $exchange => $queue) {
            $this->subscribeToExchange(
                $bus,
                $outputPrinter,
                $this
                    ->router
                    ->getExchangeByAlias($exchange),
                $queue
            );
        }

        $this
            ->loop
            ->run();
    }

    /**
     * Subscribe to queue.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param string        $exchangeName
     * @param string        $queueName
     */
    private function subscribeToExchange(
        Bus $bus,
        OutputPrinter $outputPrinter,
        string $exchangeName,
        string $queueName
    ) {
        $promise = !empty($queueName)
            ? new FulfilledPromise($queueName)
            : $this
                ->channel
                ->queueDeclare('', false, true, true)
                ->then(function (MethodQueueDeclareOkFrame $okFrame) use ($exchangeName) {
                    $queueName = $okFrame->queue;

                    return $this
                        ->channel
                        ->queueBind($queueName, $exchangeName)
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
                        return $this->dispatchEvent(
                            $bus,
                            unserialize($message->content),
                            $outputPrinter,
                            function () use ($message) {
                                $this->channel->ack($message);
                            },
                            function () use ($message) {
                                $this->channel->ack($message);
                            }
                        );
                    }, $queueName, '', false, false, true);
            })
            ->otherwise(function (\Throwable $throwable) use ($outputPrinter) {
                (new EventBusHeaderMessage(
                    '',
                    'The consumer has thrown an exception - '.$throwable->getMessage()
                ))->print($outputPrinter);
                (new EventBusHeaderMessage('', 'Consumer stopped'))->print($outputPrinter);
            });
    }
}
