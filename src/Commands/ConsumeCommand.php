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
use Th3Mouk\RxTraining\Consumers\SimpleBufferedConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleDisconnectedConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleDuplicateConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleLooperConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleProducerConsumer;
use Th3Mouk\RxTraining\Consumers\SimpleTimedConsumer;

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

        switch ($type) {
            case 'timed':
                $consumer = new SimpleTimedConsumer($output);
                break;

            case 'buffered':
                $consumer = new SimpleBufferedConsumer($output);
                break;

            case 'duplicate':
                $consumer = new SimpleDuplicateConsumer($output);
                break;

            case 'looper':
                $consumer = new SimpleLooperConsumer($output);
                break;

            case 'produce':
                $consumer = new SimpleProducerConsumer($output);
                break;

            case 'disconnected':
                $consumer = new SimpleDisconnectedConsumer($output);
                break;

            case 'backbuffer':
                $consumer = new GorillaBackBufferConsumer($output);
                break;

            default:
                $consumer = new SimpleConsumer($output);
                break;
        }

        $consumer->consume();
    }
}
