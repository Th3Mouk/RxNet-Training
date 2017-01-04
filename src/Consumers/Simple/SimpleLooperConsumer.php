<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use Rx\Observer\CallbackObserver;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\RabbitMq\RabbitQueue;

class SimpleLooperConsumer extends SimpleBaseConsumer
{
    /**
     * The binding key to use when an order goes to preparation
     */
    const ROUTING_KEY_PIZZA = 'pizza.ordering';

    /**
     * @var \Rxnet\RabbitMq\RabbitQueue
     */
    private $queue;

    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $this->queue = $this->rabbit->queue('simple_queue', []);
        $this->queue->setQos(1);

        $exchange = $this->rabbit->exchange('amq.direct');

        $this->queue->create(RabbitQueue::DURABLE)
            ->zip([
                $exchange->create($exchange::TYPE_DIRECT, [
                    $exchange::DURABLE,
                    $exchange::AUTO_DELETE
                ]),
                // Bind on a routing key (here pizza.ordering)
                $this->queue->bind(self::ROUTING_KEY_PIZZA, 'amq.direct')
            ])
            ->doOnNext(function () {
                $this->output->writeln("<info>Exchange, and queue are created and bounded</info>");
            })
            // Everything's done let's produce
            ->subscribeCallback(function () use ($exchange) {
                $this->queue->consume()
                    ->subscribe(new CallbackObserver(function (RabbitMessage $message) use ($exchange) {
                        $observable = \Rx\Observable::just($message->getData());
                        $observable->share();

                        $observable->subscribe(new CallbackObserver(function ($datas) use ($exchange, $message) {
                            $message->ack();

                            \Rx\Observable::just($datas)
                                ->flatMap(function ($datas) use ($exchange) {
                                    // Rabbit will handle serialize and unserialize
                                    return $exchange->produce($datas, self::ROUTING_KEY_PIZZA);
                                })
                                ->subscribe(new CallbackObserver());
                        }));

                        $observable->subscribe(new CallbackObserver(function ($datas) {
                            if (isset($datas['type']) && $datas['type'] === 'looper') {
                                $this->output->writeln('<error>+1 tour</error>');
                            } else {
                                $perso_name = $datas['name'];
                                $this->output->writeln('<info>Just loop on ' . $perso_name . ' order</info>');
                            }
                        }));
                    }));
            });
        $this->loop->run();
    }
}
