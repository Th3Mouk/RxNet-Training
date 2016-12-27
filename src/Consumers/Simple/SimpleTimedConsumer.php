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
 * This consumer consume a message each second
 */
class SimpleTimedConsumer
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
     * SimpleTimedConsumer constructor.
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

        $queue = $this->rabbit->queue('simple_queue', []);
        $queue->setQos(1);

        // Will wait for message
        $queue->consume()
            ->delay(1000)
            ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
                $data = $message->getData();
                $perso_name = $data['name'];

                $this->output->writeln('<info>Just received '.$perso_name.' order</info>');

                $message->ack();
            }), new EventLoopScheduler($this->loop));

        $this->loop->run();
    }
}
