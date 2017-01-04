<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;

/**
 * This consumer consume 3 messages each 2 seconds
 */
class SimpleBufferedConsumer extends SimpleBaseConsumer
{
    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(3);

        // Will wait for message
        $queue->consume()
            // This operator stack messages and return [] of message
            ->bufferWithCount(3)
            ->delay(1000)
            // So here we receive an array and not a RabbitMessage or Observable
            ->subscribe(new CallbackObserver(function (array $messages) {
                foreach ($messages as $message) {
                    $data = $message->getData();
                    $perso_name = $data['name'];

                    $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                    $message->ack();
                }
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
