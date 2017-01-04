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

class SimpleRoutableConsumer extends SimpleBaseConsumer
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

        $queue = $this->rabbit->queue('simple_second_queue');
        $this->exchange = $this->rabbit->exchange('amq.direct');

        // This consumer is an alternative from SimpleProducerConsumer
        $queue->create($queue::DURABLE)
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
            // Everything's done let's produce
            ->subscribe(new CallbackObserver(function () {
                $queue = $this->rabbit->queue('simple_queue');
                $queue->setQos(1);

                // Will wait for message
                $queue->consume()
                    // Here the CallbackObserver is extract into an other
                    // function for readability
                    ->subscribe($this->producer());
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }

    private function producer()
    {
        return new CallbackObserver(function (RabbitMessage $message) {
            $data = $message->getData();
            $perso_name = $data['name'];

            $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

            $subject = new \Rxnet\Routing\RoutableSubject(
                $message->getRoutingKey(),
                $message->getData(),
                $message->getLabels()
            );

            // A routable subject is a promise that can be executed whenever
            // in the future by sending an event.
            // In our case we add an additionnal operator that give 5s to
            // produce or the subject is rejected
            $subject
                // Only take the first event for the timeout, else 5s after the
                // first event completion (at the second empty attempt) an
                // error will be throwned.
                ->take(1)
                ->timeout(5000)
                ->flatMap(function ($datas) {
                    // Rabbit will handle serialize and unserialize
                    return $this->exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
                })
                ->subscribeCallback(
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                        $message->ack();
                    },
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<error>Something wrong with '.$datas['name'].' order</error>');
                        $message->reject();
                    },
                    null,
                    new EventLoopScheduler($this->loop)
                );

            // Here we are cheating by calling ourselves the message production
            // This example is pretty bad because it's not a real case.
            $subject->onNext(new \Rxnet\Event\Event('Start'));
        });
    }
}
