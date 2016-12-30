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
use Rxnet\Redis\Redis;
use Symfony\Component\Console\Output\OutputInterface;
use Th3Mouk\RxTraining\Extractors\RabbitExtractor;

/**
 * Class RedisMessageFilterOperator
 * @package Th3Mouk\RxTraining\Operators
 */
class RedisMessageFilterOperator implements OperatorInterface
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $path;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var RabbitMessage
     */
    private $message;

    /**
     * @var string
     */
    private $key;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var boolean
     */
    private $isInRedis;

    /**
     * RedisFilterOperator constructor.
     * @param OutputInterface $output
     * @param Redis $redis
     * @param string $path
     * @param int $ttl
     */
    public function __construct(OutputInterface $output, Redis $redis, string $path, int $ttl)
    {
        $this->output = $output;
        $this->redis = $redis;
        $this->path = $path;
        $this->ttl = $ttl;
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
                    $this->message = $message;
                    $this->key = RabbitExtractor::extract($message, $this->path);

                    return Observable::just($message)
                        ->flatMap(function () {
                            return $this->redis->exists($this->key)
                                ->flatMap(function ($existInRedis) {
                                    $this->isInRedis = (bool) $existInRedis;

                                    if (true === $this->isInRedis) {
                                        $this->output->writeln('<comment>Ignore double on '.$this->key.' label</comment>');
                                        $this->message->ack();
                                        return Observable::emptyObservable();
                                    }
                                    return Observable::just($this->message);
                                });
                        })
                        ->subscribe(
                            new CallbackObserver(function () use ($observer) {
                                $observer->onNext($this->message);
                            }),
                            new EventLoopScheduler(EventLoop::getLoop())
                        );
                },
                function ($e) {
                    echo "Something wrong in redis message filter: ".print_r($e);
                },
                function () {
                    if (false === $this->isInRedis) {
                        $this->redis->setEx($this->key, $this->ttl, true);
                    }
                }
            ), new EventLoopScheduler(EventLoop::getLoop()));
    }
}
