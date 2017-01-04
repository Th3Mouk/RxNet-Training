# RabbitMQ & RxNet

**Reactive Programming**

How to cook the :rabbit: with 
[RxNet library](https://github.com/Domraider/rxnet).

This is a simple test project, to learn how to produce and consume messages, 
whatever interupts.

**Start from zero to hero.**

You can learn with documented consumers, and a new notion each time.

## Learn is fun

You can practice with following chapters, but if you prefer theory you have 
here the glossary of concepts:

1. [How to consume](/src/Consumers/Simple/SimpleConsumer.php)
2. [How to handle disconnetion](/src/Consumers/Simple/SimpleDisconnectedConsumer.php)
3. [How to consume with interval](/src/Consumers/Simple/SimpleTimedConsumer.php)
4. [How to wait severals message before starting](/src/Consumers/Simple/SimpleBufferedConsumer.php)
5. [How to consume and then produce](/src/Consumers/Simple/SimpleProducerConsumer.php)
6. [How to consume and then produce with routable subjects](/src/Consumers/Simple/SimpleRoutableConsumer.php)
7. [How to consume and handle special messages](/src/Consumers/Simple/SimpleLooperConsumer.php)
8. [How to consume and ignore duplicate messages](/src/Consumers/Simple/SimpleDuplicateConsumer.php)

## Installation

Install Docker and Kitematic, and so click on install RabbitMQ.

Don't forget to configure ports of RabbitMQ on your machine.

Clone the project and launch `composer install`.

#### Redis

For some consumer you need to install a Redis container.

## Test

To test if the project is installed on your machine

`php bin/run hello`

## Start producer

This command start to produce a number of message.

`php bin/run produce`

**Options**
- type: default `orders`
    - `loop` (produce a simply message for a looper)

- orders: default `20`

## Consume messages

There is several way to consume messages.

`php bin/run consume`

**Options**
- type: default `simple`
    - `timed` (consume a message each second)
    - `buffered` (consume 3 messages each two seconds)
    - `duplicate` (deduplicate identical messages)
    - `looper` (simply loop on a queue to detect a full iteration)
    - `produce` (produce a message at reception)
    - `disconnected` (handle the disconnection of rabbit servor)
    - `backbuffer` (handle disconnection of production in memory)
    - `routable` (simply consume in another way)
    
- level: default `easy`
    - `hard` (use operators)
    
**Example**
`php bin/run consume --type=buffered --level=hard`

## I'm a noob OMG help me

Read the special [FAQ](resources/doc/hello-im-noob.md).

**Started from the bottom now we here !**
