<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use Rxnet\RabbitMq\RabbitMessage;
use Th3Mouk\RxTraining\Extractors\RabbitExtractor;

class SimpleDuplicateConsumer extends SimpleBaseConsumer
{
    /**
     * @var string
     */
    protected $path = 'delivery';

    /**
     * @var int
     */
    protected $seconds = 10;

    public function start()
    {
        $redis = new \Rxnet\Redis\Redis();

        // Wait for rabbit and redis to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());
        \Rxnet\awaitOnce($redis->connect('127.0.0.1:6379'));

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            // The use of the flatMap let us make an asynch operation
            ->flatMap(function (RabbitMessage $message) use ($redis) {
                $label = RabbitExtractor::extract($message, $this->path);

                // The operation to know if an entry already exist in redis is
                // asynch. So you need to subscribe to the returned observable
                return $redis->exists($label)
                    ->filter(function ($existInRedis) use ($message, $redis, $label) {
                        $existInRedis = (bool) $existInRedis;
                        // If message already exists then just ignore it
                        if (true === $existInRedis) {
                            $this->output->writeln('<comment>Ignore double on '.$label.' label</comment>');
                            $message->ack();
                            // By returning false into a filter, all the event
                            // chain is stopped for this message
                            return false;
                        }

                        return true;
                    })
                    // We must return the message for simplicity in the subscribe
                    ->map(function () use ($message) {
                        return $message;
                    });
            })
            ->subscribeCallback(function (RabbitMessage $message) use ($redis) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                $label = RabbitExtractor::extract($message, $this->path);

                // Here you must record in redis the message
                $redis->setEx($label, $this->seconds, true);
                // And then unshift the message from the queue
                $message->ack();
            });

        $this->loop->run();
    }
}
