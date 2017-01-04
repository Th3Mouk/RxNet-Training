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

class SimpleDisconnectedConsumer extends SimpleBaseConsumer
{
    /**
     * Use to store a disposable in case of problem to restart consumption
     * @var Rx\DisposableInterface
     */
    protected $consumer;

    /**
     * @var \Rxnet\RabbitMq\RabbitQueue
     */
    protected $queue;

    public function start()
    {
        $this->rabbit->connect()
            // This operator will catch any error thrown by the rabbit
            // connection. And relaunch the connect method.
            ->retryWhen(function ($errors) {
                // When the error is catched we stand 2s before trying to
                // reconnect to not bruteforce the reconnection
                return $errors->delay(2000)
                    // When 2s are elapsed we prompted a message and we start
                    // the reconnection
                    ->doOnNext(function () {
                        echo "Rabbit is disconnected, retrying\n";
                    });
            })
            ->subscribe(new CallbackObserver(function () {
                // If we are here, the connection is restablished but we need
                // to restart the consumption. To do that we need to dispose
                // the disposable return by the subscription to the consumer.
                // A dispose let the possibility to close all stream and event
                // binded on the loop
                if ($this->consumer) {
                    $this->consumer->dispose();
                }

                $this->queue = $this->rabbit->queue('simple_queue', []);
                $this->queue->setQos(1);

                // Don't forget to store the return of the subscription
                // The disposable is returned before any execution so it can be
                // disposed at any moment.
                $this->consumer = $this->queue->consume()
                    ->delay(2000)
                    ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
                        $data = $message->getData();
                        $perso_name = $data['name'];

                        $message->ack();

                        $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');
                    }), new EventLoopScheduler($this->loop));
            }), new EventLoopScheduler($this->loop));
    }
}
