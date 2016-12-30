<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Operators;

use EventLoop\EventLoop;
use Rx\Observable;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\Scheduler\EventLoopScheduler;
use Rx\SchedulerInterface;
use Rxnet\RabbitMq\RabbitMessage;
use Symfony\Component\Console\Output\OutputInterface;

class LoopDetectorOperator implements OperatorInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * LoopDetectorOperator constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param \Rx\ObservableInterface $observable
     * @param \Rx\ObserverInterface $observer
     * @param \Rx\SchedulerInterface $scheduler
     * @return \Rx\DisposableInterface
     */
    public function __invoke(ObservableInterface $observable, ObserverInterface $observer, SchedulerInterface $scheduler = null)
    {
        return $observable
            ->subscribe(new CallbackObserver(
                function (RabbitMessage $message) use ($observer) {
                    $datas = $message->getData();
                    if (isset($datas['type']) && $datas['type'] === 'looper') {
                        $this->output->writeln('<fg=magenta>+1 tour</>');
                        $message
                            ->rejectToBottom()
                            ->subscribe(
                                new CallbackObserver(),
                                new EventLoopScheduler(EventLoop::getLoop())
                            );

                        $observer->onCompleted();
                    }
                    $observer->onNext($message);
                }
            ), new EventLoopScheduler(EventLoop::getLoop())
        );
    }
}
