<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;

class SimpleConsumer extends SimpleBaseConsumer
{
    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->subscribeCallback(function (RabbitMessage $message) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                // Do what you want but do one of this to get next
                $message->ack();
                //$message->nack();
                //$message->reject();
                //$message->rejectToBottom();
            }, null, null, new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
