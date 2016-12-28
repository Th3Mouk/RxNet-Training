<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Th3Mouk\RxTraining\Commands\Styles\SuccessTrait;
use Th3Mouk\RxTraining\Consumers\AnotherSimpleDisconnectedConsumer;
use Th3Mouk\RxTraining\Consumers\GorillaBackBufferConsumer;
use Th3Mouk\RxTraining\Consumers\Hard\HardCombinedConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleBufferedConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleDisconnectedConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleDuplicateConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleLooperConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleProducerConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleRoutableConsumer;
use Th3Mouk\RxTraining\Consumers\Simple\SimpleTimedConsumer;

class ConsumeCommand extends Command
{
    use SuccessTrait;

    protected function configure()
    {
        $this
            // the name of the command
            ->setName('consume')

            // the short description of the command
            ->setDescription('Consume some dumb pizza ordering.')

            // select difficulty of consumer to use
            ->addOption(
                'level',
                null,
                InputOption::VALUE_REQUIRED,
                'Difficulty of consumer to use',
                'simple'
            )

            // select which consumer to use
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Type of consumer to use',
                'simple'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()->setStyle('leaf', $this->getSuccessStyle());
        $type = $input->getOption('type');

        if ($input->getOption('level') === 'hard') {
            $consumer = $this->hard($output, $type);
        } else {
            $consumer = $this->easy($output, $type);
        }

        $consumer->start();
    }

    private function easy(OutputInterface $output, string $type)
    {
        switch ($type) {
            case 'timed':
                return new SimpleTimedConsumer($output);

            case 'buffered':
                return new SimpleBufferedConsumer($output);

            case 'duplicate':
                return new SimpleDuplicateConsumer($output);

            case 'looper':
                return new SimpleLooperConsumer($output);

            case 'produce':
                return new SimpleProducerConsumer($output);

            case 'disconnected':
                return new SimpleDisconnectedConsumer($output);

            case 'backbuffer':
                return new GorillaBackBufferConsumer($output);

            case 'routable':
                return new SimpleRoutableConsumer($output);

            default:
                return new SimpleConsumer($output);
        }
    }

    private function hard(OutputInterface $output, string $type)
    {
        switch ($type) {
            default:
                return new HardCombinedConsumer($output);
        }
    }
}
