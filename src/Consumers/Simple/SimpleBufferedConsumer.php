<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use EventLoop\EventLoop;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;

/**
 * This consumer consume 3 messages each 2 seconds
 */
class SimpleBufferedConsumer
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
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->bufferWithCount(3)
            ->doOnNext(function () {
                sleep(2);
            })
            ->subscribeCallback(function (array $messages) use ($loop, $rabbit) {
                foreach ($messages as $message) {
                    $data = $message->getData();
                    $perso_name = $data['name'];

                    $head = $message->getLabels();

                    $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                    // Do what you want but do one of this to get next
                    $message->ack();
                    //$message->nack();
                    //$message->reject();
                    //$message->rejectToBottom();
                }
            });

        $loop->run();
    }
}
