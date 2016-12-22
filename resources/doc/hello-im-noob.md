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
No real answer yet just infinite loop i think so.

#### How works the invoke ?

[PHPDoc](http://php.net/manual/fr/language.oop5.magic.php#object.invoke)

#### Why must I use the port 5672 of RabbitMQ ?

'Cause it's the default value of `tcp_listeners`. [Doc](https://www.rabbitmq.com/configure.html)

#### How does work subscribeCallback method ?

First is the callback before the next iteration, second the closure to handle
errors and finally the callback when submission is complete.

#### Call to undefined method React\Promise\Promise::flatMap()

Check that version of [Bunny](https://github.com/Domraider/bunny) is the 
latest (dev-master is the problem persist).

#### The class RabbitQueue lack of PHP documentation.

## Consumer

#### OMG too difficult to understand how to make a delay between message consume.

After understood where to put the operator it will be cool.

#### So much operators... 

Easy (Ezzz) there is a 
[wonderful doc](http://reactivex.io/documentation/operators.html).

#### How tow filter element with a function and time limitation

Because we probably get multiple consumer running in parallel, we need a Redis.

The second question is how to expire datas in Redis.

#### How to connect a Redis ? Is it relevant ?

Absolutly yeuus !
Simply use RxNet and enter host configuration.

Init it, config it, inject it, erase it.

#### How to not lose a message ?

Declare your queue as durable. Once you consume it don't forget to 
`$message->ack();` to remove it from the queue.

#### How to easily expire a value in Redis ?

Use the [TTL](https://redis.io/commands/ttl) and the 
[Expire](https://redis.io/commands/expire)

#### Why the Redis exist always return null ?

It not return null but an observable, think to use the flatmap operator.

#### How manage parallelism ? Seems the consumer get all messages at once

On this way the consumer only get one message at a time.

```php
$queue->setQos(1);
```

#### My consumer doesn't restart when connection is restablished

You need to listen the next callback of your retryWhen.

#### [Error] ImmediateScheduler does not support a non-zero delay.

You need a EventLoopScheduler in your subscribtion.

#### My channel or my queue is null

So your rabbit isn't connected at runtime.

#### Why using subscribe instead of subscribeCallback ?

If you don't handle onError and onComplete with callbacks, but you manipulate
the time like delay, timeout..., you will put a `SchedulerInterface` as last 
argument (`null, null, new EventLoopScheduler(...)`).

For readibily you can use subscribe a `new CallbackObserver(...)` in first
argument of subscribe method, instead of 3 arguments with subscribeCallback, 
and then your `EventLoopScheduler(...)` in second argument.
