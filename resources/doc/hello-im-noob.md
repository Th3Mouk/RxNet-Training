# Noobie FAQ 

I start from the postulate that you have a default install of RabbitMQ 
container with Docker/Kitematic.

## Use RabbitMQ

Connexion to human interface: 
`http://rabbit:15672/` or `http://localhost:15672/`

#### Why this URL doesn't works ?

Because the port configuration of RabbitMQ container on Kitematic isn't good.
You must set `15672` = `15672`. 

#### What is my login/mdp ->
[RTFM](https://hub.docker.com/_/rabbitmq/) guest/guest

## Producer

#### What is an event loop ?

~~[Soluce1](https://github.com/voryx/event-loop)~~
~~[Soluce2](https://github.com/reactphp/event-loop)~~
It's simply a loop that ticking every 0.1ms, and check if a binded closure
must be called. Use only `EventLoop:getLoop()` on your schedulers !

#### How works the invoke magic method ?

[PHPDoc](http://php.net/manual/fr/language.oop5.magic.php#object.invoke)

#### Why must I use the port `5672` of RabbitMQ ?

'Cause it's the default value of `tcp_listeners`. [Doc](https://www.rabbitmq.com/configure.html)

#### How does work subscribeCallback method ?

First is the callback called when the event chain arrive to subscribe, second 
the closure to handle errors and finally the callback when the complete signal
is dispatched.

#### Call to undefined method React\Promise\Promise::flatMap()

Check that version of [Bunny](https://github.com/Domraider/bunny) is the 
latest (dev-master is the problem persist).

#### The class RabbitQueue lack of PHP documentation.

It's a fact but she's not so much complicated.

## Consumer

#### OMG too difficult to understand how to make a delay between message consume.

After understood where to put the operator it will be cool.
Simply add a delay operator after the consume.

#### So much operators... 

Easy (Ezzz) there is a 
[wonderful doc](http://reactivex.io/documentation/operators.html).
[RxMarbles](http://rxmarbles.com/) is pretty cool too to understood how they 
works.

#### How to filter element with a function and time limitation

Because we probably get multiple consumer running in parallel, we need a Redis.

The second question is how to expire datas in Redis. (U can use setEx 
:simple_smile:)

#### How to connect a Redis ? Is it relevant ?

Absolutly yeuus !
Simply use RxNet and enter host configuration.

Init it, config it, inject it, erase it.

#### How to not lose a message ?

Declare your queue as durable. Once you consume it, don't forget to 
`$message->ack();` to remove it from the queue.

#### How to easily expire a value in Redis ?

Use the [TTL](https://redis.io/commands/ttl) and the 
[Expire](https://redis.io/commands/expire).

#### Why the Redis exist always return null ?

It not return null but an observable, think to use the flatmap operator because
redis operation are asynch.

#### How manage parallelism ? Seems the consumer get all messages at once

```php
$queue->setQos(1);
```

On this way the consumer only get one message at a time.

#### My consumer doesn't restart when connection is restablished

The better example is the following [SimpleDisconnectedConsumer](/src/Consumers/Simple/SimpleDisconnectedConsumer.php).
You need to listen the rabbit connection, and so add a `retryWhen` closure, to
handle errors.

It's better, but Rabbit will still not consume :fearful:.
 
To do that you need to store the the disposable of the consumer in an (class)
attribute. And when your consumer restart you can dispose the old one and 
start the new one.

#### [Error] ImmediateScheduler does not support a non-zero delay.

You need a EventLoopScheduler in your subscription.

#### My channel or my queue is null

So your rabbit isn't connected at runtime you need to wait connection assertion
before publish/consume.

#### Why using subscribe instead of subscribeCallback ?

If you don't handle onError and onComplete with callbacks, but you manipulate
the time like delay, timeout..., you will put a `SchedulerInterface` as last 
argument (`function(){...}, null, null, new EventLoopScheduler(...)`).

For readibily you can use subscribe a `new CallbackObserver(...)` in first
argument of subscribe method, instead of 3 arguments with subscribeCallback, 
and then your `EventLoopScheduler(...)` in second argument.

#### Lack of docs on routable subjects

It's a fact too, routable subjects are 'improved' promise. This allow to return
a promise that can be called later (when you want).

#### Why my loop of production with backpressure dont fully iterate ?

If an operator trigger the `onComplete` method of your interval observable,
any event (like buffer) are stopped.

Don't plug `take` operator and `backpressure` together, use a subscriber on 
`take` to detach the event chain.
 
#### [Error] Argument 1 passed to Rx\Operator\MergeAllOperator::Rx\Operator\{closure}() must implement interface Rx\ObservableInterface, null given

You probably break the chain of events.
Check if a map or a flatMap or something else don't miss a return.

#### Hell how this can be functional ?

[code](https://github.com/ReactiveX/RxPHP/blob/4807ab11285bb3f5e665cff2ead766d72f775a87/lib/Rx/Operator/CountOperator.php#L45)

#### I'm tired to stub onNext, onError and onComplete

Use the StOutObserver to debug easily in place of CallbackObserver.

#### How to manage error when one of multiple service is deconnected ? (ex: rabbit/redis)

Use `retryWhen` multiple times on each service you want to connect.

## General Observation

The events doesn't 'bubble', after all `onNext` the chain don't ascend back all 
`onComplete` closure. The `onComplete` start from the top of the chain.

We can see an observable like a context.

If you want to call your `onComplete` in operator or whatever, make sur to end
your observable.
