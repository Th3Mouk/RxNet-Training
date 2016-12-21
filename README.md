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

## Test

To test if the project is installed on your machine

`php run hello`

## Start producer

This command start to produce a number of message.

`php run produce`

**Options**
- orders: default `20`

## Consume messages

There is several way to consume messages.

`php run consume`

**Options**
- type: default `simple`
    - `timed` (consume a message each second)
    - `buffered` (consume 3 messages each two seconds)
    
**Example**
`php run consume --type=buffered`

## I'm a f***ing noob OMG help me

Read the special [FAQ](resources/doc/hello-im-noob.md).

**Started from the bottom now we here !**
