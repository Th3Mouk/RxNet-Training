<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Hard;

use EventLoop\EventLoop;
use Rx\Observable;
use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;
use Rx\Subject\Subject;
use Rxnet\Event\Event;
use Rxnet\RabbitMq\RabbitMessage;
use Rxnet\Routing\RoutableSubject;
use Symfony\Component\Console\Output\OutputInterface;
use Th3Mouk\RxTraining\Operators\RedisMessageFilterOperator;

class HardCombinedConsumer
{
    /**
     * The binding key to use when an order goes to preparation
     */
    const ROUTING_KEY_PIZZA_PREPARATION = 'pizza.preparation';

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
     * @var \Rxnet\Redis\Redis
     */
    private $redis;

    /**
     * @var \Rxnet\RabbitMq\RabbitExchange
     */
    private $exchange;

    private $consumer;

    /**
     * HardCombinedConsumer constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->loop = EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
        $this->redis = new \Rxnet\Redis\Redis();
    }

    public function start()
    {
        // Wait redis connection before start something
        \Rxnet\awaitOnce($this->redis->connect('127.0.0.1:6379'));

        // Wait for rabbit to be connected
        $this->rabbit
            ->connect()
            ->retryWhen(function ($errors) {
                return $errors
                    ->delay(2000)
                    ->doOnNext(function () {
                        $this->output->writeln('<fg=red>Rabbit is disconnected, retrying</>');
                    });
            })
            ->subscribe(
                $this->bindProducer(),
                new EventLoopScheduler($this->loop)
            );

        $this->loop->run();
    }

    private function bindProducer()
    {
        return new CallbackObserver(function () {
            $queue = $this->rabbit->queue('simple_second_queue');
            $this->exchange = $this->rabbit->exchange('amq.direct');

            // Start an observable sequence
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
                ->subscribe(
                    $this->consume(),
                    new EventLoopScheduler($this->loop)
                );
        });
    }

    private function consume()
    {
        return new CallbackObserver(function () {
            $queue = $this->rabbit->queue('simple_queue');
            $queue->setQos(1);

            if ($this->consumer) {
                $this->consumer->dispose();
            }

            $this->consumer = $queue->consume()
                ->delay(1000)
                ->flatMap($this->checkLoop())
                ->flatMap($this->checkProduce())
                ->subscribe(
                    new CallbackObserver(),
                    new EventLoopScheduler($this->loop)
                );
        });
    }

    private function checkProduce()
    {
        return function (RabbitMessage $message) {
            $messageStream = new Subject();

            $messageStream
                ->lift(function () {
                    return new RedisMessageFilterOperator(
                        $this->output, $this->redis, 'delivery', 2
                    );
                })
                ->flatMap($this->produce())
                ->doOnNext(function(Event $event) use ($messageStream) {
                    if ($event->is('/complete')) {
                        $messageStream->onCompleted();
                    }
                })
                ->subscribe(
                    new CallbackObserver(),
                    new EventLoopScheduler($this->loop)
                );

            $messageStream->onNext($message);

            return $messageStream;
        };
    }

    private function produce()
    {
        return function (RabbitMessage $message) {
            $data = $message->getData();

            if (isset($data['name'])) {
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');
            }

            $subject = new RoutableSubject(
                $message->getRoutingKey(),
                $message,
                $message->getLabels()
            );

            // Give 2s to handle the subject or reject it to bottom (with all its changes)
            $subject
                ->filter(function(Event $event){
                    return !$event->is('/complete');
                })
                ->flatMap(function () use ($message) {
                    // Rabbit will handle serialize and unserialize
                    return $this->exchange->produce($message->getData(), self::ROUTING_KEY_PIZZA_PREPARATION);
                })
                ->take(1)
                ->timeout(2000)
                ->subscribeCallback(
                    // Ignore onNext
                    null,
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<error>Something wrong with '.$datas['name'].' order</error>');
                        $message->rejectToBottom();
                    },
                    function () use ($message, $subject) {
                        $datas = $message->getData();
                        $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                        $message->ack();

                        $subject->onNext(new Event('/complete'));
                    },
                    new EventLoopScheduler($this->loop)
            );

            $subject->onNext(new Event('Lets produce'));

            return $subject;
        };
    }

    private function checkLoop($callable = null)
    {
        return function (RabbitMessage $message) use ($callable) {
            $datas = $message->getData();
            if (isset($datas['type']) && $datas['type'] === 'looper') {
                $this->output->writeln('<fg=magenta>+1 tour</>');
                $message
                    ->rejectToBottom()
                    ->subscribe(
                        new CallbackObserver(),
                        new EventLoopScheduler($this->loop)
                    );
                return Observable::emptyObservable();
            }

            return Observable::just($message);
        };
    }
}
