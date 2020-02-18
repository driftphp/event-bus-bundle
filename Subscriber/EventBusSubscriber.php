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

namespace Drift\EventBus\Subscriber;

use Drift\Console\OutputPrinter;
use Drift\EventBus\Async\AsyncAdapter;
use Drift\EventBus\Bus\InlineEventBus;

/**
 * Class EventBusSubscriber.
 */
final class EventBusSubscriber
{
    /**
     * @var InlineEventBus
     */
    private $inlineEventBus;

    /**
     * @var AsyncAdapter
     */
    private $asyncAdapter;

    /**
     * ExternalInlineEventBus constructor.
     *
     * @param InlineEventBus $inlineEventBus
     * @param AsyncAdapter   $asyncAdapter
     */
    public function __construct(
        InlineEventBus $inlineEventBus,
        AsyncAdapter $asyncAdapter
    ) {
        $this->inlineEventBus = $inlineEventBus;
        $this->asyncAdapter = $asyncAdapter;
    }

    /**
     * Subscribe to exchanges.
     *
     * @param array         $exchanges
     * @param OutputPrinter $outputPrinter
     */
    public function subscribeToExchanges(
        array $exchanges,
        OutputPrinter $outputPrinter
    ) {
        $this
            ->asyncAdapter
            ->subscribe(
                $this->inlineEventBus,
                $outputPrinter,
                $exchanges
            );
    }

    /**
     * Get async adapter name.
     *
     * @return string
     */
    public function getAsyncAdapterName(): string
    {
        return $this
            ->asyncAdapter
            ->getName();
    }
}
