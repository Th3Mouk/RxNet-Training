<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Producers;

use Rx\Observable;
use Rx\Scheduler\EventLoopScheduler;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Th3Mouk\RxTraining\Model\Order;

class PizzaOrderingProducer
{
    /**
     * The binding key to use when a pizza is ordered
     */
    const ROUTING_KEY_PIZZA = 'pizza.ordering';

    /**
     * @var int
     */
    private $orders;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * PizzaOrderingProducer constructor.
     * @param OutputInterface $output
     * @param int $orders
     */
    public function __construct(OutputInterface $output, int $orders)
    {
        $this->orders = $orders;
        $this->output = $output;
    }

    public function produce()
    {
        $loop = \EventLoop\EventLoop::getLoop();
        $rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());

        // Wait for rabbit to be up (lazy way)
        \Rxnet\awaitOnce($rabbit->connect());

        $queue = $rabbit->queue('simple_queue', []);
        $exchange = $rabbit->exchange('amq.direct');

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
            ->subscribeCallback(function () use ($exchange, $loop) {

                // Initialise some variables
                $done = 0;
                $start = microtime(true);
                $progress = new ProgressBar($this->output, $this->orders);
                $progress->start();

                // Here we produce the message (huuum quite pizza)
                // Don't send object or array of object !
                // Use JSON, array or string
                Observable::interval(50)
                    // Replace the message index by usefull datas
                    // This must be done because of "foreach" and dumb datas
                    ->map(function () {
                        return Order::generate();
                    })
                    ->take($this->orders)
                    //->take(function () use (&$done) {
                    //    return $done < $this->orders;
                    //})
                    // Wait for one produce to be done before starting another
                    ->flatMap(function ($data) use ($exchange) {
                        // Rabbit will handle serialize and unserialize
                        return $exchange->produce($data, self::ROUTING_KEY_PIZZA);
                    })
                    // Let's get some stats
                    ->subscribeCallback(
                        function () use (&$done, $progress) {
                            $done++;
                            $progress->advance();
                        }, null,
                        function () use (&$done, $progress, $start, $loop) {
                            $progress->finish();
                            $progress->clear();
                            $this->output->writeln('<leaf>'.number_format($done) . ' lines produced in ' . (microtime(true) - $start) . 'ms</leaf>');
                            $loop->stop();
                        },
                        new EventLoopScheduler($loop)
                    );
            });

        $loop->run();
    }
}
