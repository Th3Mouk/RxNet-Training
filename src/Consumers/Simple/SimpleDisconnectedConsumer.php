<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use Rx\DisposableInterface;
use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;

class SimpleDisconnectedConsumer extends SimpleBaseConsumer
{
    /**
     * @var DisposableInterface
     */
    protected $consumer;

    /**
     * @var \Rxnet\RabbitMq\RabbitQueue
     */
    protected $queue;

    public function start()
    {
        $this->rabbit->connect()
            ->retryWhen(function ($errors) {
                return $errors->delay(2000)
                    ->doOnNext(function () {
                        echo "Rabbit is disconnected, retrying\n";
                    });
            })
            ->subscribe(new CallbackObserver(function () {
                if ($this->consumer) {
                    $this->consumer->dispose();
                }

                $this->queue = $this->rabbit->queue('simple_queue', []);
                $this->queue->setQos(1);

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
