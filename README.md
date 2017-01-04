# RabbitMQ & RxNet

**Reactive Programming**

How to cook the :rabbit: with 
[RxNet library](https://github.com/Domraider/rxnet).

This is a simple test project, to learn how to produce and consume messages, 
whatever interupts.

**Start from 0 to hero.**

## Installation

Install Docker and Kitematic, and so click on install RabbitMQ.

Don't forget to configure ports of RabbitMQ on your machine.

Clone the project and launch `composer install`.

### Redis

For some consumer you need to install a Redis container.

## Test

To test if the project is installed on your machine

`php run hello`

## Start producer

This command start to produce a number of message.

`php run produce`

**Options**
- type: default `orders`
    - `loop` (produce a simply message for a looper)

- orders: default `20`

## Consume messages

There is several way to consume messages.

`php run consume`

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
`php run consume --type=buffered --level=hard`

## I'm a noob OMG help me

Read the special [FAQ](resources/doc/hello-im-noob.md).

**Started from the bottom now we here !**
