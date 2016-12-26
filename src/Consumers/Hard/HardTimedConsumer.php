<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Hard;

use EventLoop\EventLoop;
use Rx\Observer\CallbackObserver;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;
use Th3Mouk\RxTraining\Operators\SleepOperator;

/**
 * This consumer consume a message each second
 */
class HardTimedConsumer
{
    /**
     * @var Output
     */
    private $output;

    /**
     * HardTimedConsumer constructor.
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
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->lift(function () {
                return new SleepOperator(1);
            })
            ->subscribe(new CallbackObserver(function (RabbitMessage $message) use ($loop, $rabbit) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                $message->ack();
            }));

        $loop->run();
    }
}
