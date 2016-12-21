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
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;
use Th3Mouk\RxTraining\Extractors\RabbitExtractor;

/**
 * This consumer consume messages each seconds
 */
class SimpleDuplicateConsumer
{
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
        $redis = new \Rxnet\Redis\Redis();


        // Wait for rabbit and redis to be connected
        \Rxnet\awaitOnce($rabbit->connect());
        \Rxnet\awaitOnce($redis->connect('127.0.0.1:6379'));

        $path = 'delivery';
        // A whole minute hardcoded
        $seconds = 10;

        $queue = $rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->flatMap(function (RabbitMessage $message) use ($redis, $path, $seconds) {
                $label = RabbitExtractor::extract($message, $path);
                return $redis->exists($label)
                    ->filter(function ($existInRedis) use ($message, $redis, $label, $seconds) {
                        $existInRedis = (bool) $existInRedis;
                        // If message already exists then just ignore it
                        if (true === $existInRedis) {
                            $this->output->writeln('<comment>Ignore double on '.$label.' label</comment>');
                            $message->ack();
                            return false;
                        }

                        return true;
                    })
                    ->map(function () use ($message) {
                        return $message;
                    });
            })
            ->subscribeCallback(function (RabbitMessage $message) use ($loop, $rabbit, $redis, $path, $seconds) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $head = $message->getLabels();

                $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                $label = RabbitExtractor::extract($message, $path);

                // Here you must record in redis the message
                $redis->setEx($label, $seconds, true);

                // Do what you want but do one of this to get next
                $message->ack();
                //$message->nack();
                //$message->reject();
                //$message->rejectToBottom();
            });

        $loop->run();
    }
}
