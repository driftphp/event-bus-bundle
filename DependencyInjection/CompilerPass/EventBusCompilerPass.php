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

use Drift\AMQP\DependencyInjection\CompilerPass\AMQPCompilerPass;
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
use Exception;
use React\EventLoop\LoopInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class EventBusCompilerPass.
 */
final class EventBusCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        list($isAsync, $passThrough, $asyncAdapter) = self::checkAsyncAdapters(
            $container,
            $container->getParameter('bus.event_bus.async_adapter'),
            $container->getParameter('bus.event_bus.async_pass_through')
        );

        self::createBuses(
            $container,
            $isAsync,
            $passThrough,
            $container->getParameter('bus.event_bus.distribution'),
            $container->getParameter('bus.event_bus.middlewares'),
        );

        if ($isAsync) {
            self::createAsyncBus(
                $container,
                $asyncAdapter,
                $passThrough,
                $container->getParameter('bus.event_bus.routes'),
                $container->getParameter('bus.event_bus.exchanges'),
            );
        }
    }

    /**
     * Create Buses.
     *
     * @param ContainerBuilder $container
     * @param bool             $asyncBus
     * @param bool             $passThrough
     * @param string $distribution
     * @param array $middlewares
     *
     * @return void
     */
    public static function createBuses(
        ContainerBuilder $container,
        bool $asyncBus,
        bool $passThrough,
        string $distribution,
        array $middlewares
    ): void {
        self::createEventBus($container, $asyncBus, $passThrough, $distribution, $middlewares);
        self::createInlineEventBus($container, $distribution, $middlewares);
        self::createBusDebugger($container);
    }

    /**
     * Create Async.
     *
     * @param ContainerBuilder $container
     * @param array            $asyncAdapter
     * @param bool             $passThrough
     * @param array $routes
     * @param array $exchanges
     *
     * @return void
     */
    public static function createAsyncBus(
        ContainerBuilder $container,
        array $asyncAdapter,
        bool $passThrough,
        array $routes,
        array $exchanges
    ): void {
        self::createAsyncMiddleware($container, $asyncAdapter, $passThrough, $routes, $exchanges);
        self::createEventBusSubscriber($container);
        self::createEventConsumer($container);
        self::createInfrastructureCreateCommand($container);
        self::createInfrastructureDropCommand($container);
        self::createInfrastructureCheckCommand($container);
    }

    /**
     * Check for async middleware needs and return if has been created.
     *
     * @param ContainerBuilder $container
     * @param array            $asyncAdapter
     * @param bool             $passThrough
     * @param array $routes
     * @param array $exchanges
     *
     * @return void
     */
    public static function createAsyncMiddleware(
        ContainerBuilder $container,
        array $asyncAdapter,
        bool $passThrough,
        array $routes,
        array $exchanges
    ): void {
        $container->setDefinition(Router::class,
            new Definition(
                Router::class, [
                    $routes,
                    $exchanges,
                ]
            )
        );

        $adapterType = $asyncAdapter['type'];

        switch ($adapterType) {
            case 'amqp':
                self::createAMQPAsyncAdapter($container, $asyncAdapter);
                break;
            case 'in_memory':
                self::createInMemoryAsyncAdapter($container);
                break;
        }

        $container->setDefinition(AsyncMiddleware::class,
            new Definition(
                AsyncMiddleware::class, [
                    new Reference(AsyncAdapter::class),
                    $passThrough,
                ]
            )
        );
    }

    /**
     * Check asnc configuration.
     *
     * Returns an array with
     *
     * - isAsync => Async is enabled
     * - passThrough => Event bus must be passThrough
     * - adapter => Used adapter if async is enabled
     *
     * @param ContainerBuilder $container
     * @param array            $asyncAdapters
     * @param bool             $passThrough
     *
     * @return array
     */
    private static function checkAsyncAdapters(
        ContainerBuilder $container,
        array $asyncAdapters,
        bool $passThrough
    ): array {
        if (
            empty($asyncAdapters) ||
            (
                isset($asyncAdapters['adapter']) &&
                !'in_memory' === $asyncAdapters['adapter'] &&
                !isset($asyncAdapters[$asyncAdapters['adapter']])
            )
        ) {
            return [false, true, null];
        }

        $adapterType = $asyncAdapters['adapter'] ?? array_key_first($asyncAdapters);
        $adapterType = $container->resolveEnvPlaceholders($adapterType, true);
        $adapter = $asyncAdapters[$adapterType] ?? null;

        switch ($adapterType) {
            case 'amqp':
            case 'in_memory':
                break;
            default:
                if (isset($asyncAdapters['adapter'])) {
                    throw new Exception('Wrong adapter. Please use one of this list: amqp, in_memory.');
                }

                return [false, true, null];
        }

        $adapter['type'] = $adapterType;

        return [true, $passThrough, $adapter];
    }

    /**
     * Create event bus.
     *
     * @param ContainerBuilder $container
     * @param bool             $asyncBus
     * @param bool             $passThrough
     * @param string $distribution
     * @param array $middlewares
     */
    private static function createEventBus(
        ContainerBuilder $container,
        bool $asyncBus,
        bool $passThrough,
        string $distribution,
        array $middlewares
    ) {
        $container->setDefinition('drift.event_bus', (new Definition(
            EventBus::class, [
                new Reference(LoopInterface::class),
                new Reference(AsyncEventDispatcherInterface::class),
                self::createMiddlewaresArray(
                    $container,
                    $asyncBus,
                    $passThrough,
                    $middlewares
                ),
                $distribution,
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
     * @param string $distribution
     * @param array $middlewares
     */
    private static function createInlineEventBus(
        ContainerBuilder $container,
        string $distribution,
        array $middlewares
    )
    {
        $container->setDefinition('drift.inline_event_bus', (new Definition(
            InlineEventBus::class, [
                new Reference(LoopInterface::class),
                new Reference(AsyncEventDispatcherInterface::class),
                self::createMiddlewaresArray(
                    $container,
                    false,
                    true,
                    $middlewares
                ),
                $distribution,
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
     * @param array $definedMiddlewares
     *
     * @return array
     */
    private static function createMiddlewaresArray(
        ContainerBuilder $container,
        bool $isAsync,
        bool $passthrough,
        array $definedMiddlewares
    ) {
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
    private static function createEventBusSubscriber(ContainerBuilder $container)
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
    private static function createEventConsumer(ContainerBuilder $container)
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
    private static function createBusDebugger(ContainerBuilder $container)
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
    private static function createInfrastructureCreateCommand(ContainerBuilder $container)
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
    private static function createInfrastructureDropCommand(ContainerBuilder $container)
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
    private static function createInfrastructureCheckCommand(ContainerBuilder $container)
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
    private static function createAMQPAsyncAdapter(
        ContainerBuilder $container,
        array $adapter
    ) {
        $adapter['preload'] = true;
        AMQPCompilerPass::registerClient($container, 'event_bus', $adapter);

        $container->setDefinition(
            AsyncAdapter::class,
            (
            new Definition(AMQPAdapter::class, [
                new Reference('amqp.event_bus_channel'),
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
    private static function createInMemoryAsyncAdapter(ContainerBuilder $container)
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
