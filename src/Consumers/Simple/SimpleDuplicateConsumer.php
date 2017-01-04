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

/**
 * This consumer consume messages each seconds
 */
class SimpleDuplicateConsumer extends SimpleBaseConsumer
{
    public function start()
    {
        $redis = new \Rxnet\Redis\Redis();

        // Wait for rabbit and redis to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());
        \Rxnet\awaitOnce($redis->connect('127.0.0.1:6379'));

        $path = 'delivery';
        // A whole minute hardcoded
        $seconds = 10;

        $queue = $this->rabbit->queue('simple_queue', []);
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
            ->subscribeCallback(function (RabbitMessage $message) use ($redis, $path, $seconds) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                $label = RabbitExtractor::extract($message, $path);

                // Here you must record in redis the message
                $redis->setEx($label, $seconds, true);

                $message->ack();
            });

        $this->loop->run();
    }
}
