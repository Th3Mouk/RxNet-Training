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
use Th3Mouk\RxTraining\Producers\PizzaOrderingProducer;

class ProduceCommand extends Command
{
    use SuccessTrait;

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('produce')

            // the short description shown while running "php bin/console list"
            ->setDescription('Generate some dumb pizza ordering.')

            // number of pizza ordering
            ->addOption(
                'orders',
                null,
                InputOption::VALUE_REQUIRED,
                'How many pizza orders do you want?',
                20
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()->setStyle('leaf', $this->getSuccessStyle());
        $orders = $input->getOption('orders');

        $pizza_producer = new PizzaOrderingProducer($output, $orders);
        $pizza_producer->produce();
    }
}
