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

use Drift\EventBus\Bus\Bus;
use Drift\EventBus\Bus\EventBus;
use Drift\EventBus\Bus\InlineEventBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DebugEventBusCommand.
 */
class DebugEventBusCommand extends Command
{
    protected static $defaultName = 'debug:event-bus';

    /**
     * @var EventBus
     */
    private $eventBus;

    /**
     * @var InlineEventBus
     */
    private $inlineEventBus;

    /**
     * BusDebugger constructor.
     *
     * @param EventBus       $eventBus
     * @param InlineEventBus $inlineEventBus
     */
    public function __construct(
        EventBus $eventBus,
        InlineEventBus $inlineEventBus
    ) {
        parent::__construct();

        $this->eventBus = $eventBus;
        $this->inlineEventBus = $inlineEventBus;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setDescription('Dumps the event bus configuration, including middlewares');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $this->printBus('Event', $this->eventBus, $output);
        $this->printBus('Inline Event', $this->inlineEventBus, $output);

        return 0;
    }

    /**
     * Print bus.
     *
     * @param string          $name
     * @param EventBus        $bus
     * @param OutputInterface $output
     */
    private function printBus(
        string $name,
        Bus $bus,
        OutputInterface $output
    ) {
        $output->writeln("  $name Bus  ");
        $output->writeln('----------------------');
        $middlewareList = $bus->getMiddlewareList();

        foreach ($middlewareList as $middleware) {
            $output->writeln('  - '.$middleware['class'].'::'.$middleware['method']);
        }

        $output->writeln('');
        $output->writeln('');
    }
}
