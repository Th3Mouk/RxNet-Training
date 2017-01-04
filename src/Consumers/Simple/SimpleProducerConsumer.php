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

    /**
     * @var \Rxnet\RabbitMq\RabbitExchange
     */
    protected $exchange;

    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_second_queue', []);
        $this->exchange = $this->rabbit->exchange('amq.direct');

        // Before doing something else it's more convenient to bind your
        // producer. If you do it into the consumption, you will rebind
        // at each message received.
        $queue->create($queue::DURABLE)
            // This operator wait the completion of all observable in param
            // And 'zip' all result into one Observable
            ->zip([
                $this->exchange->create(($this->exchange)::TYPE_DIRECT, [
                    ($this->exchange)::DURABLE,
                    ($this->exchange)::AUTO_DELETE
                ]),
                // Bind on a routing key (here pizza.preparation)
                $queue->bind(self::ROUTING_KEY_PIZZA_PREPARATION, 'amq.direct')
            ])
            ->doOnNext(function () {
                $this->output->writeln("<info>Exchange, and queue are created and bounded</info>");
            })
            // Everything's done let's consume
            ->subscribeCallback(function () {
                $queue = $this->rabbit->queue('simple_queue', []);
                $queue->setQos(1);

                // Will wait for message
                $queue->consume()
                    ->subscribeCallback(function (RabbitMessage $message) {
                        $data = $message->getData();
                        $perso_name = $data['name'];

                        $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                        // Create an observable to produce into a queue because
                        // this operation is asynch
                        \Rx\Observable::just($message->getData())
                            ->flatMap(function ($datas) {
                                // Rabbit will handle serialize and unserialize
                                return $this->exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
                            })
                            // You're done, here you have produce your message
                            ->subscribeCallback(
                                null, null,
                                function () use ($message) {
                                    $datas = $message->getData();
                                    $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                                    // And the finality you can say 'YAY', I've
                                    // fully handle my message
                                    $message->ack();
                                }
                            );
                    }, null, null, new EventLoopScheduler($this->loop));
            });

        $this->loop->run();
    }
}
