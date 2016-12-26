<?php

/*
 * (c) Jérémy Marodon <marodon.jeremy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Th3Mouk\RxTraining\Operators;

use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\SchedulerInterface;

class SleepOperator implements OperatorInterface
{
    /**
     * @var integer
     */
    protected $delay;

    /**
     * Sleep constructor.
     * @param int $delay
     */
    public function __construct(int $delay)
    {
        $this->delay = $delay;
    }

    public function __invoke(ObservableInterface $observable, ObserverInterface $observer, SchedulerInterface $scheduler = null)
    {
        $callbackObserver = new CallbackObserver(
            function ($x) use ($observer) {
                sleep($this->delay);
                $observer->onNext($x);
            },
            function () use ($observer) {
                $observer->onError(new \Exception('Sleep operator error'));
            },
            null
        );

        return $observable->subscribe($callbackObserver, $scheduler);
    }
}
