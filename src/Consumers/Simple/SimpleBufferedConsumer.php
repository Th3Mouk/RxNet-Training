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
use Symfony\Component\Console\Output\Output;

/**
 * This consumer consume 3 messages each 2 seconds
 */
class SimpleBufferedConsumer
{
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
     * SimpleBufferedConsumer constructor.
     * @param Output $output
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
        $this->loop = EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
    }

    public function consume()
    {
        // Wait for rabbit to be connected
        \Rxnet\awaitOnce($this->rabbit->connect());

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(3);

        // Will wait for message
        $queue->consume()
            ->bufferWithCount(3)
            ->delay(1000)
            ->subscribe(new CallbackObserver(function (array $messages) {
                foreach ($messages as $message) {
                    $data = $message->getData();
                    $perso_name = $data['name'];

                    $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');

                    // Do what you want but do one of this to get next
                    $message->ack();
                }
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
