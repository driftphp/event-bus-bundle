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

namespace Drift\EventBus\DependencyInjection\CompilerPass;

use Drift\EventBus\Async\AMQPAdapter;
use Drift\EventBus\Async\AsyncAdapter;
use Drift\EventBus\Async\InMemoryAdapter;
use Drift\EventBus\Bus\EventBus;
use Drift\EventBus\Bus\InlineEventBus;
use Drift\EventBus\Console\DebugEventBusCommand;
use Drift\EventBus\Console\EventConsumerCommand;
use Drift\EventBus\Console\InfrastructureCheckCommand;
use Drift\EventBus\Console\InfrastructureCreateCommand;
use Drift\EventBus\Console\InfrastructureDropCommand;
use Drift\EventBus\Middleware\AsyncMiddleware;
use Drift\EventBus\Middleware\Middleware;
use Drift\EventBus\Router\Router;
use Drift\EventBus\Subscriber\EventBusSubscriber;
use Drift\HttpKernel\AsyncEventDispatcherInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class EventBusCompilerPass.
 */
class EventBusCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        list($asyncBus, $passThrough) = $this->createAsyncMiddleware($container);

        $this->createEventBus($container, $asyncBus, $passThrough);
        $this->createInlineEventBus($container);
        $this->createBusDebugger($container);

        if ($asyncBus) {
            $this->createEventBusSubscriber($container);
            $this->createEventConsumer($container);
            $this->createInfrastructureCreateCommand($container);
            $this->createInfrastructureDropCommand($container);
            $this->createInfrastructureCheckCommand($container);
        }
    }

    /**
     * Check for async middleware needs and return if has been created.
     *
     * @param ContainerBuilder $container
     *
     * @return array
     */
    public function createAsyncMiddleware(ContainerBuilder $container): array
    {
        $asyncAdapters = $container->getParameter('bus.event_bus.async_adapter');

        if (
            empty($asyncAdapters) ||
            (
                isset($asyncAdapters['adapter']) &&
                !'in_memory' === $asyncAdapters['adapter'] &&
                !isset($asyncAdapters[$asyncAdapters['adapter']])
            )
        ) {
            return [false, true];
        }

        $container->setDefinition(Router::class,
            new Definition(
                Router::class, [
                    '%bus.event_bus.routes%',
                    '%bus.event_bus.exchanges%',
                ]
            )
        );

        $adapterType = $asyncAdapters['adapter'] ?? array_key_first($asyncAdapters);
        $adapter = $asyncAdapters[$adapterType] ?? null;

        switch ($adapterType) {
            case 'amqp':
                $this->createAMQPAsyncAdapter($container, $adapter);
                break;
            case 'in_memory':
                $this->createInMemoryAsyncAdapter($container);
                break;
            default:
                return [false, true];
        }

        $passThrough = $asyncAdapters['pass_through'];
        $container->setDefinition(AsyncMiddleware::class,
            new Definition(
                AsyncMiddleware::class, [
                    new Reference(AsyncAdapter::class),
                    $passThrough,
                ]
            )
        );

        return [true, $passThrough];
    }

    /**
     * Create event bus.
     *
     * @param ContainerBuilder $container
     * @param bool             $asyncBus
     * @param bool             $passThrough
     */
    private function createEventBus(
        ContainerBuilder $container,
        bool $asyncBus,
        bool $passThrough
    ) {
        $container->setDefinition('drift.event_bus', (new Definition(
            EventBus::class, [
                new Reference(LoopInterface::class),
                new Reference(AsyncEventDispatcherInterface::class),
                $this->createMiddlewaresArray(
                    $container,
                    $asyncBus,
                    $passThrough
                ),
                $container->getParameter('bus.event_bus.distribution'),
                $passThrough,
            ]
        ))->addTag('preload')
        );

        $container->setAlias(EventBus::class, 'drift.event_bus');
    }

    /**
     * Create inline event bus.
     *
     * @param ContainerBuilder $container
     */
    private function createInlineEventBus(ContainerBuilder $container)
    {
        $container->setDefinition('drift.inline_event_bus', (new Definition(
            InlineEventBus::class, [
                new Reference(LoopInterface::class),
                new Reference(AsyncEventDispatcherInterface::class),
                $this->createMiddlewaresArray(
                    $container
                ),
                $container->getParameter('bus.event_bus.distribution'),
                true,
            ]
        ))->addTag('preload')
        );

        $container->setAlias(InlineEventBus::class, 'drift.inline_event_bus');
    }

    /**
     * Create array of middlewares by configuration.
     *
     * @param ContainerBuilder $containerr
     * @param bool             $isAsync
     * @param bool             $passthrough
     *
     * @return array
     */
    private function createMiddlewaresArray(
        ContainerBuilder $container,
        bool $isAsync = false,
        bool $passthrough = true
    ) {
        $definedMiddlewares = $container->getParameter('bus.event_bus.middlewares');
        $asyncFound = array_search('@async', $definedMiddlewares);
        $middlewares = [];

        if (!$asyncFound && $isAsync) {
            $middlewares[] = new Reference(AsyncMiddleware::class);

            /*
             * Only skip other middlewares if is defined as not passthrough
             */
            if (!$passthrough) {
                return $middlewares;
            }
        }

        foreach ($definedMiddlewares as $middleware) {
            if ('@async' === $middleware) {
                if ($isAsync) {
                    $middlewares[] = new Reference(AsyncMiddleware::class);

                    if (!$passthrough) {
                        return $middlewares;
                    }
                }

                continue;
            }

            $method = 'dispatch';
            $splitted = explode('::', $middleware, 2);
            if (2 === count($splitted)) {
                $middleware = $splitted[0];
                $method = $splitted[1];
            }

            if (!$container->has($middleware)) {
                $container->setDefinition($middleware, new Definition($middleware));
            }

            $middlewareWrapperName = "{$middleware}\\Wrapper";
            $middlewareWrapper = new Definition(Middleware::class, [
                new Reference($middleware),
                $method,
            ]);

            $container->setDefinition($middlewareWrapperName, $middlewareWrapper);
            $middlewares[] = new Reference($middlewareWrapperName);
        }

        return $middlewares;
    }

    /**
     * Console Commands.
     */

    /**
     * Create event bus subscriber.
     *
     * @param ContainerBuilder $container
     */
    private function createEventBusSubscriber(ContainerBuilder $container)
    {
        $subscriber = new Definition(EventBusSubscriber::class, [
            new Reference('drift.inline_event_bus'),
            new Reference(AsyncAdapter::class),
        ]);

        $subscriber->setPublic(true);
        $container->setDefinition(EventBusSubscriber::class, $subscriber);
    }

    /**
     * Create event consumer.
     *
     * @param ContainerBuilder $container
     */
    private function createEventConsumer(ContainerBuilder $container)
    {
        $consumer = new Definition(EventConsumerCommand::class, [
            new Reference(EventBusSubscriber::class),
            new Reference('reactphp.event_loop'),
        ]);

        $consumer->addTag('console.command', [
            'command' => 'event-bus:consume-events',
        ]);

        $container->setDefinition(EventConsumerCommand::class, $consumer);
    }

    /**
     * Create command consumer.
     *
     * @param ContainerBuilder $container
     */
    private function createBusDebugger(ContainerBuilder $container)
    {
        $consumer = new Definition(DebugEventBusCommand::class, [
            new Reference(EventBus::class),
            new Reference(InlineEventBus::class),
        ]);

        $consumer->addTag('console.command', [
            'command' => 'debug:event-bus',
        ]);

        $container->setDefinition(DebugEventBusCommand::class, $consumer);
    }

    /**
     * Create infrastructure creator.
     *
     * @param ContainerBuilder $container
     */
    private function createInfrastructureCreateCommand(ContainerBuilder $container)
    {
        $consumer = new Definition(InfrastructureCreateCommand::class, [
            new Reference(AsyncAdapter::class),
            new Reference('reactphp.event_loop'),
        ]);

        $consumer->addTag('console.command', [
            'command' => 'event-bus:infra:create',
        ]);

        $container->setDefinition(InfrastructureCreateCommand::class, $consumer);
    }

    /**
     * Create infrastructure dropper.
     *
     * @param ContainerBuilder $container
     */
    private function createInfrastructureDropCommand(ContainerBuilder $container)
    {
        $consumer = new Definition(InfrastructureDropCommand::class, [
            new Reference(AsyncAdapter::class),
            new Reference('reactphp.event_loop'),
        ]);

        $consumer->addTag('console.command', [
            'command' => 'event-bus:infra:drop',
        ]);

        $container->setDefinition(InfrastructureDropCommand::class, $consumer);
    }

    /**
     * Create infrastructure checker.
     *
     * @param ContainerBuilder $container
     */
    private function createInfrastructureCheckCommand(ContainerBuilder $container)
    {
        $consumer = new Definition(InfrastructureCheckCommand::class, [
            new Reference(AsyncAdapter::class),
            new Reference('reactphp.event_loop'),
        ]);

        $consumer->addTag('console.command', [
            'command' => 'event-bus:infra:check',
        ]);

        $container->setDefinition(InfrastructureCheckCommand::class, $consumer);
    }

    /**
     * ADAPTERS.
     */

    /**
     * Create amqp async adapter.
     *
     * @param ContainerBuilder $container
     * @param array            $adapter
     */
    private function createAMQPAsyncAdapter(
        ContainerBuilder $container,
        array $adapter
    ) {
        $container->setDefinition(
            AsyncAdapter::class,
            (
            new Definition(AMQPAdapter::class, [
                new Reference('amqp.'.$adapter['client'].'_channel'),
                new Reference(Router::class),
            ])
            )->setLazy(true)
        );
    }

    /**
     * Create in_memory async adapter.
     *
     * @param ContainerBuilder $container
     */
    private function createInMemoryAsyncAdapter(ContainerBuilder $container)
    {
        $container->setDefinition(
            AsyncAdapter::class,
            (
                new Definition(InMemoryAdapter::class, [
                    new Reference(Router::class),
                ])
            )->setLazy(true)
        );
    }
}
