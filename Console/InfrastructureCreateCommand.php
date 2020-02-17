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

use Clue\React\Block;
use Drift\Console\OutputPrinter;
use Drift\EventBus\Async\AsyncAdapter;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InfrastructureCreateCommand.
 */
class InfrastructureCreateCommand extends EventBusCommand
{
    /**
     * @var AsyncAdapter
     */
    private $asyncAdapter;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * ConsumeCommand constructor.
     *
     * @param AsyncAdapter  $asyncAdapter
     * @param LoopInterface $loop
     */
    public function __construct(
        AsyncAdapter $asyncAdapter,
        LoopInterface $loop
    ) {
        parent::__construct();

        $this->asyncAdapter = $asyncAdapter;
        $this->loop = $loop;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Creates the infrastructure to make the command bus work asynchronously');
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force the action'
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
        if (!$input->getOption('force')) {
            (new EventBusHeaderMessage('', 'Please, use the flag --force'))->print($outputPrinter);

            return 1;
        }

        $adapterName = $this->asyncAdapter->getName();
        (new EventBusHeaderMessage('', 'Started building infrastructure...'))->print($outputPrinter);
        (new EventBusHeaderMessage('', 'Using adapter '.$adapterName))->print($outputPrinter);

        try {
            $promise = $this
                ->asyncAdapter
                ->createInfrastructure(
                    $this->buildQueueArray($input),
                    $outputPrinter
                )
                ->then(function () use ($outputPrinter) {
                    (new EventBusHeaderMessage('', 'Infrastructure created'))->print($outputPrinter);
                });
        } catch (\Throwable $throwable) {
            (new EventBusLineMessage(sprintf(
                'Exception thrown. Reason - %s',
                $throwable->getMessage()
            )))->print($outputPrinter);

            return 1;
        }

        Block\await($promise, $this->loop);

        return 0;
    }
}
