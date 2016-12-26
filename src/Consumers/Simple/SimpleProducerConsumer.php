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

class SimpleProducerConsumer
{
    /**
     * The binding key to use when an order goes to preparation
     */
    const ROUTING_KEY_PIZZA_PREPARATION = 'pizza.preparation';

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
            ->subscribeCallback(function (RabbitMessage $message) use ($loop, $rabbit) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                $queue = $rabbit->queue('simple_second_queue', []);
                $exchange = $rabbit->exchange('amq.direct');

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
                    ->subscribeCallback(function () use ($exchange, $loop, $message) {
                        \Rx\Observable::just($message->getData())
                            ->flatMap(function ($datas) use ($exchange) {
                                // Rabbit will handle serialize and unserialize
                                return $exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
                            })
                            // Let's get some stats
                            ->subscribeCallback(
                                null, null,
                                function () use ($loop, $message) {
                                    $datas = $message->getData();
                                    $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                                    $message->ack();
                                }
                            );
                    });
            }, null, null, new EventLoopScheduler($loop));

        $loop->run();
    }
}
