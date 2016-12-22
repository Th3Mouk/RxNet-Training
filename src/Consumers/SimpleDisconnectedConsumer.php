<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers;

use EventLoop\EventLoop;
use Rx\DisposableInterface;
use Rx\Observer\CallbackObserver;
use Rx\Scheduler\EventLoopScheduler;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\Output;

class SimpleDisconnectedConsumer
{
    /**
     * @var Output
     */
    private $output;

    /**
     * @var \Rxnet\RabbitMq\RabbitMq
     */
    protected $rabbit;

    /**
     * @var DisposableInterface
     */
    protected $consumer;

    /**
     * @var \React\EventLoop\LibEventLoop
     */
    protected $loop;

    /**
     * @var \Rxnet\RabbitMq\RabbitQueue
     */
    protected $queue;

    /**
     * SimpleDisconnectedConsumer constructor.
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
        $this->rabbit->connect()
            ->retryWhen(function ($errors) {
                return $errors->delay(2000)
                    ->doOnNext(function () {
                        echo "Rabbit is disconnected, retrying\n";
                    });
            })
            ->subscribe(new CallbackObserver(function () {
                if ($this->consumer) {
                    $this->consumer->dispose();
                }

                $this->queue = $this->rabbit->queue('simple_queue', []);
                $this->queue->setQos(1);

                $this->consumer = $this->queue->consume()
                    ->delay(2000)
                    ->subscribe(new CallbackObserver(function (RabbitMessage $message) {
                        $data = $message->getData();
                        $perso_name = $data['name'];

                        // Do what you want but do one of this to get next
                        $message->ack();
                        //$message->nack();
                        //$message->reject();
                        //$message->rejectToBottom();

                        $this->output->writeln('<info>Just received ' . $perso_name . ' order</info>');
                    }), new EventLoopScheduler($this->loop));
            }), new EventLoopScheduler($this->loop));
    }
}
