<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers;

use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;

/**
 * This consumer consume a message each second
 */
class PizzaOrderingTimedConsumer
{
    /**
     * @var Output
     */
    private $output;

    /**
     * PizzaOrderingConsumer constructor.
     * @param Output $output
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    public function consume()
    {
        $loop = EventLoop::getLoop();
        $rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($rabbit->connect());

        $queue = $rabbit->queue('simple_queue', []);

        // Will wait for message
        $queue->consume()
            ->doOnNext(function () {
                sleep(1);
            })
            ->subscribeCallback(function (RabbitMessage $message) use ($loop, $rabbit) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $name = $message->getName();
                $head = $message->getLabels();

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                $message->ack();
            });

        $loop->run();
    }
}
