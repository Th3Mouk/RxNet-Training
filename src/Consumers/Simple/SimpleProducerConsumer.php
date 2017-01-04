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

class SimpleProducerConsumer extends SimpleBaseConsumer
{
    /**
     * The binding key to use when an order goes to preparation
     */
    const ROUTING_KEY_PIZZA_PREPARATION = 'pizza.preparation';

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

                $queue = $this->rabbit->queue('simple_second_queue', []);
                $exchange = $this->rabbit->exchange('amq.direct');

                // Start an observable sequence
                $queue->create($queue::DURABLE)
                    ->zip([
                        $exchange->create($exchange::TYPE_DIRECT, [
                            $exchange::DURABLE,
                            $exchange::AUTO_DELETE
                        ]),
                        // Bind on a routing key (here pizza.preparation)
                        $queue->bind(self::ROUTING_KEY_PIZZA_PREPARATION, 'amq.direct')
                    ])
                    ->doOnNext(function () {
                        $this->output->writeln("<info>Exchange, and queue are created and bounded</info>");
                    })
                    // Everything's done let's produce
                    ->subscribeCallback(function () use ($exchange, $message) {
                        \Rx\Observable::just($message->getData())
                            ->flatMap(function ($datas) use ($exchange) {
                                // Rabbit will handle serialize and unserialize
                                return $exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
                            })
                            // Let's get some stats
                            ->subscribeCallback(
                                null, null,
                                function () use ($message) {
                                    $datas = $message->getData();
                                    $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                                    $message->ack();
                                }
                            );
                    });
            }, null, null, new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
