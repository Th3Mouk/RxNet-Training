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
use Th3Mouk\RxTraining\Operators\LoopDetectorOperator;
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

    /**
     * This method start redis and handle rabbit disconnection
     */
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
            // Once all is connected we can bind a producer
            ->subscribe(
                $this->bindProducer(),
                new EventLoopScheduler($this->loop)
            );

        $this->loop->run();
    }

    /**
     * Manage the binding of a producer
     *
     * @return CallbackObserver
     */
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
                // Everything's done let's consuming
                ->subscribe(
                    $this->consume(),
                    new EventLoopScheduler($this->loop)
                );
        });
    }

    /**
     * This method is responsible of consuming the queue and manage the
     * rabbit restart
     *
     * @return CallbackObserver
     */
    private function consume()
    {
        return new CallbackObserver(function () {
            $queue = $this->rabbit->queue('simple_queue');
            $queue->setQos(1);

            if ($this->consumer) {
                $this->consumer->dispose();
            }

            $this->consumer = $queue->consume()
                ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
                    // Here we need to create our own 'context', an observable.
                    // Because in this callback we need to perform a onComplete
                    // event. And it will terminate the consume, and it's bad,
                    // we just want to complete the message event, not the
                    // consuming
                    Observable::just($message)
                        ->delay(1000)
                        // This operator only listen on the next event and he's
                        // a filter for special messages.
                        // Some on them are reject to the bottom of the queue
                        // and the event chain is stopped, the consumer send a
                        // new message.
                        ->lift(function () {
                            return new LoopDetectorOperator(
                                $this->output,
                                function ($datas) {
                                    return (isset($datas['type']) && $datas['type'] === 'looper') ? true : false;
                                }
                            );
                        })
                        // We can now check if we need to produce an new message
                        ->flatMap($this->checkProduce())
                        ->subscribe(
                            new CallbackObserver(),
                            new EventLoopScheduler($this->loop)
                        );
                }), new EventLoopScheduler($this->loop));
        });
    }

    /**
     * Here we check if message aren't not duplicated and all Redis operations
     *
     * @return \Closure
     */
    private function checkProduce()
    {
        return function (RabbitMessage $message) {
            // A subject is an extend of an Observable
            // In our case it's an open stream, waiting a rabbit message
            $messageStream = new Subject();

            $messageStream
                ->lift(function () {
                    return new RedisMessageFilterOperator(
                        $this->output, $this->redis, 'delivery', 2
                    );
                })
                // Here we produce the message if Redis operator don't filter
                ->flatMap($this->produce())
                // The produce method return events here.
                // If the event report a completion it's here that we trigger
                // complete manually the end of the stream and the Redis
                // operator onComplete callback.
                ->doOnNext(function (Event $event) use ($messageStream) {
                    if ($event->is('pizza.ordering.complete')) {
                        $messageStream->onCompleted();
                    }
                })
                ->subscribe(
                    new CallbackObserver(),
                    new EventLoopScheduler($this->loop)
                );

            // Once our stream is declared we send the rabbit message into him
            // to start the event chain.
            $messageStream->onNext($message);

            // And we return our (stream/subject) observable to the flatMap
            return $messageStream;
        };
    }

    /**
     * Produce the message in another queue
     *
     * @return \Closure
     */
    private function produce()
    {
        return function (RabbitMessage $message) {
            $data = $message->getData();

            if (isset($data['name'])) {
                $perso_name = $data['name'];
                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');
            }

            // Here we create a routable subject that recall himself when he
            // add finished to produce
            $subject = new RoutableSubject(
                $message->getRoutingKey(),
                $message->getData(),
                $message->getLabels()
            );

            // Give 2s to handle the subject or reject it to bottom (with all its changes)
            $subject
                // First if a complete event arrive here 'STOP' !
                // We have already produce so don't do it again
                ->filter(function (Event $event) {
                    return !$event->is('pizza.ordering.complete');
                })
                // Wait producing
                ->flatMap(function () use ($message) {
                    return $this->exchange->produce($message->getData(), self::ROUTING_KEY_PIZZA_PREPARATION);
                })
                ->take(1)
                ->timeout(2000)
                ->subscribeCallback(
                    // Ignore onNext
                    null,
                    function () use ($message) {
                        $datas = $message->getData();
                        $this->output->writeln('<error>Timeout to send '.$datas['name'].' order. Retrying.</error>');
                        $message->rejectToBottom();
                    },
                    function () use ($message, $subject) {
                        // The timeout send to onComplete and not to onNext /!\
                        $datas = $message->getData();
                        $this->output->writeln('<leaf>Preparation of '.$datas['name'].' order started</leaf>');
                        // All is done so you can ack the message because
                        // if Redis didn't write, it doesn't matter
                        $message->ack();

                        // Dispatch the great news, we finished !
                        $subject->onNext(new Event('pizza.ordering.complete'));
                    },
                    new EventLoopScheduler($this->loop)
            );

            // Now all is binding, let's start producing !
            $subject->onNext(new Event('pizza.ordering.send'));

            return $subject;
        };
    }
}
