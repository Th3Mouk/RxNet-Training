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
use Rxnet\RabbitMq\RabbitMessage;

class SimpleLooperConsumer extends SimpleBaseConsumer
{
    /**
     * The binding key to use when an order goes to preparation
     */
    const ROUTING_KEY_PIZZA = 'pizza.ordering';

    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        $queue->consume()
            ->delay(1000)
            ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
                $datas = $message->getData();

                if (isset($datas['type']) && $datas['type'] === 'looper') {
                    $this->output->writeln('<error>+1 tour</error>');
                    // If conditions match so it's a looper message then
                    // put the message simply back to the bottom of the queue
                    $message
                        ->rejectToBottom()
                        // Very important to subscribe to the reject else it
                        // will never executed
                        ->subscribe(
                            new CallbackObserver(),
                            new EventLoopScheduler($this->loop)
                        );
                    return;
                }

                $perso_name = $datas['name'];
                $this->output->writeln('<info>Just loop on ' . $perso_name . ' order</info>');
                // Then ack him and/or do whatever you want
                $message->ack();
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
