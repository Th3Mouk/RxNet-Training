<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use EventLoop\EventLoop;
use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\Routing\RoutableSubject;
use Symfony\Component\Console\Output\Output;

class SimpleRoutableConsumer
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
     * @var \React\EventLoop\LibEventLoop
     */
    private $loop;

    /**
     * @var \Rxnet\RabbitMq\RabbitMq
     */
    private $rabbit;

    /**
     * @var \Rxnet\RabbitMq\RabbitExchange
     */
    private $exchange;

    /**
     * SimpleRoutableConsumer constructor.
     * @param Output $output
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
        $this->loop = EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
    }

    public function start()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_second_queue');
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
            ->subscribe(new CallbackObserver(function () use ($exchange) {
                $queue = $this->rabbit->queue('simple_queue');
                $queue->setQos(1);

                // Will wait for message
                $queue->consume()
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

            $subject = new RoutableSubject($message->getRoutingKey(), $message->getData(), $message->getLabels());
            // Give 5s to handle the subject or reject it to bottom (with all its changes)
            $subject->timeout(5000)
                ->subscribeCallback(
                // Ignore onNext
                    null,
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<error>Something wrong with '.$datas['name'].' order</error>');
                        $message->rejectToBottom();
                    },
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                        $message->ack();
                    },
                    new EventLoopScheduler($this->loop)
                );

            $subject->flatMap(function ($datas) {
                // Rabbit will handle serialize and unserialize
                return $this->exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
            });

            $subject->onCompleted();
        });
    }
}
