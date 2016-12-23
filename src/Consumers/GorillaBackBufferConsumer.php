<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers;

use EventLoop\EventLoop;
use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\Operator\OnBackPressureBuffer;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;

class GorillaBackBufferConsumer
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
     * GorillaBackBufferConsumer constructor.
     * @param Output $output
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
        $this->loop = EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

        $this->backpressure = new OnBackPressureBuffer(
            100, // Buffer capacity
            function($next, \SplQueue $queue) {echo "Buffer overflow";}, // Callable on buffer full (nullable)
            OnBackPressureBuffer::OVERFLOW_STRATEGY_ERROR // strategy on overflow
        );
    }

    public function consume()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
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
                    ->doOnNext(function () use ($message) {
                        $message->ack();
                        $this->output->writeln("<info>Exchange, and queue are created and bounded</info>");
                    })
                    // Everything's done let's produce
                    ->subscribeCallback(function () use ($exchange, $message) {
                        \Rx\Observable::interval(500)
                            ->take(10)
                            ->subscribe(new CallbackObserver(function() use ($exchange, $message) {
                                \Rx\Observable::just($message->getData())
                                    ->delay(1000)
                                    ->lift($this->backpressure->operator())
                                    ->flatMap(function($datas) use ($exchange){
                                        $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                                        return $exchange->produce($datas, self::ROUTING_KEY_PIZZA_PREPARATION);
                                    })
                                    ->doOnNext([$this->backpressure, 'request'])
                                    ->subscribe(new CallbackObserver(), new EventLoopScheduler($this->loop));
                            }), new EventLoopScheduler($this->loop));
                    });
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
