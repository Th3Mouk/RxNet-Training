<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Producers;

use Rx\Observer\CallbackObserver;
use Symfony\Component\Console\Output\OutputInterface;

class LoopingProducer
{
    /**
     * The binding key to use when a pizza is ordered
     */
    const ROUTING_KEY_PIZZA = 'pizza.ordering';

    /**
     * @var OutputInterface
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
     * PizzaOrderingProducer constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->loop = \EventLoop\EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
    }

    public function produce()
    {
        // Wait for rabbit to be up (lazy way)
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $exchange = $this->rabbit->exchange('amq.direct');

        // Start an observable sequence
        $queue->create($queue::DURABLE)
            ->zip([
                $exchange->create($exchange::TYPE_DIRECT, [
                    $exchange::DURABLE,
                    $exchange::AUTO_DELETE
                ]),
                // Bind on a routing key (here pizza.ordering)
                $queue->bind(self::ROUTING_KEY_PIZZA, 'amq.direct')
            ])
            ->doOnNext(function () {
                $this->output->writeln("<info>Exchange, and queue are created and bounded</info>");
            })
            // Everything's done let's produce
            ->subscribeCallback(function () use ($exchange) {
                $data = ["type" => "looper"];
                $exchange->produce($data, self::ROUTING_KEY_PIZZA)
                ->subscribe(new CallbackObserver(function () {
                    $this->loop->stop();
                }));
            });

        $this->loop->run();
    }
}
