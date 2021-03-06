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

use Drift\Console\OutputPrinter;
use Drift\EventBus\Bus\Bus;
use Drift\EventBus\Exception\InvalidExchangeException;
use Drift\EventBus\Router\Router;
use Exception;
use function React\Promise\all;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * Class DummyAdapter.
 */
class InMemoryAdapter extends AsyncAdapter
{
    /**
     * @var array
     */
    private $exchanges = [];

    /**
     * @var Router
     */
    private $router;

    /**
     * InMemoryAdapter constructor.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'In Memory';
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
        $exchanges = $this
            ->router
            ->getExchangesByEvent($event);

        $promises = [];
        foreach ($exchanges as $exchange) {
            if (!isset($this->queue[$exchange])) {
                $this->exchanges[$exchange] = [];
            }

            $promises[] = resolve()
                ->then(function () use ($exchange, $event) {
                    $this->exchanges[$exchange][] = $event;
                });
        }

        return all($promises);
    }

    /**
     * Subscribe.
     *
     * @param Bus           $bus
     * @param OutputPrinter $outputPrinter
     * @param array         $exchanges
     *
     * @throws InvalidExchangeException
     * @throws Exception
     */
    public function subscribe(
        Bus $bus,
        OutputPrinter $outputPrinter,
        array $exchanges
    ) {
        throw new Exception('Method not usable');
    }

    /**
     * @return array
     */
    public function getExchanges(): array
    {
        return $this->exchanges;
    }
}
