<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Consumers\Simple;

use EventLoop\EventLoop;
use Symfony\Component\Console\Output\OutputInterface;

abstract class SimpleBaseConsumer
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var \React\EventLoop\LibEventLoop
     */
    protected $loop;

    /**
     * @var \Rxnet\RabbitMq\RabbitMq
     */
    protected $rabbit;

    /**
     * SimpleBaseConsumer constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->loop = EventLoop::getLoop();
        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
    }
}
