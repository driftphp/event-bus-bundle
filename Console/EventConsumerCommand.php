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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EventConsumerCommand.
 */
class EventConsumerCommand extends Command
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
        $this->setDescription('Start consuming asynchronous events from the event bus');
        $this->addOption(
            'queue',
            null,
            InputOption::VALUE_OPTIONAL,
            'Queue where to consume events from. If empty, a temporary one will be created.',
            ''
        );
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
        $outputPrinter = new OutputPrinter($output);
        $adapterName = $this->asyncAdapter->getName();
        (new ConsumerHeaderMessage('', 'Consumer built'))->print($outputPrinter);
        (new ConsumerHeaderMessage('', 'Using adapter '.$adapterName))->print($outputPrinter);
        (new ConsumerHeaderMessage('', 'Started listening...'))->print($outputPrinter);

        $this
            ->asyncAdapter
            ->subscribe(
                $this->eventBus,
                $outputPrinter,
                $input->getOption('queue')
            );

        return 0;
    }
}
