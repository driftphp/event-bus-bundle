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

namespace Drift\EventBus\Console;

use Drift\Console\OutputPrinter;

/**
 * Class EventConsumedLineMessage.
 */
final class EventConsumedLineMessage
{
    /**
     * @var string
     */
    const CONSUMED = 'Consumed';

    /**
     * @var string
     */
    const REJECTED = 'Rejected';

    private $class;
    private $elapsedTime;
    private $status;

    /**
     * ConsumerMessage constructor.
     *
     * @param object $event
     * @param string $elapsedTime
     * @param string $status
     */
    public function __construct(
        $event,
        string $elapsedTime,
        string $status
    ) {
        $this->class = $this->getEventName($event);
        $this->elapsedTime = $elapsedTime;
        $this->status = $status;
    }

    /**
     * Print.
     *
     * @param OutputPrinter $outputPrinter
     */
    public function print(OutputPrinter $outputPrinter)
    {
        $color = '32';
        if (self::REJECTED === $this->status) {
            $color = '31';
        }

        $outputPrinter->print("\033[01;{$color}m{$this->status}\033[0m");
        $outputPrinter->print(" {$this->class} ");
        $outputPrinter->print("(\e[00;37m".$this->elapsedTime.' | '.((int) (memory_get_usage() / 1000000))." MB\e[0m)");
        $outputPrinter->printLine();
    }

    /**
     * Get event name.
     *
     * @param object $event
     *
     * @return string
     */
    private function getEventName($event): string
    {
        $eventNamespace = get_class($event);
        $eventParts = explode('\\', $eventNamespace);

        return end($eventParts);
    }
}
