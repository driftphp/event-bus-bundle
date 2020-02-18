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
use Drift\EventBus\Async\AsyncAdapter;
use Drift\EventBus\Bus\InlineEventBus;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EventConsumerCommand.
 */
class EventConsumerCommand extends EventBusCommand
{
    /**
     * @var AsyncAdapter
     */
    private $asyncAdapter;

    /**
     * @var InlineEventBus
     */
    private $eventBus;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * ConsumeCommand constructor.
     *
     * @param AsyncAdapter   $asyncAdapter
     * @param InlineEventBus $eventBus
     * @param LoopInterface  $loop
     */
    public function __construct(
        AsyncAdapter $asyncAdapter,
        InlineEventBus $eventBus,
        LoopInterface $loop
    ) {
        parent::__construct();

        $this->asyncAdapter = $asyncAdapter;
        $this->eventBus = $eventBus;
        $this->loop = $loop;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Start consuming asynchronous events from the event bus');
    }

    /**
     * Consumes events from defined queues. For given exchanges that are not
     * defined in the queue name, temporary exchanges will be used.
     *
     * consume-events --queue myqueue --queue anotherqueue:exchange1
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputPrinter = new OutputPrinter($output);
        $adapterName = $this->asyncAdapter->getName();
        (new EventBusHeaderMessage('', 'Consumer built'))->print($outputPrinter);
        (new EventBusHeaderMessage('', 'Using adapter '.$adapterName))->print($outputPrinter);
        (new EventBusHeaderMessage('', 'Started listening...'))->print($outputPrinter);

        $this
            ->asyncAdapter
            ->subscribe(
                $this->eventBus,
                $outputPrinter,
                $this->buildQueueArray($input)
            );

        $this
            ->loop
            ->run();

        return 0;
    }
}
