# Noobie FAQ quests 

I start from the postulate that you have a default install of RabbitMQ 
container with Docker/Kitematic.

### Use RabbitMQ

Connexion to human interface: `http://rabbit:15672/` or `http://localhost:15672/`

##### What is my login/mdp ->
[RTFM](https://hub.docker.com/_/rabbitmq/) guest/guest

### Producer

##### What is an event loop ?

~~[Soluce1](https://github.com/voryx/event-loop)~~
~~[Soluce2](https://github.com/reactphp/event-loop)~~

##### How works the invoke ?

[PHPDoc](http://php.net/manual/fr/language.oop5.magic.php#object.invoke)

##### Why must I use the port 5672 of RabbitMQ ?

'Cause it's the default value of `tcp_listeners`. [Doc](https://www.rabbitmq.com/configure.html)

##### How does work subscribeCallback method ?

First is the callback before the next iteration, second the closure to handle
errors and finally the callback when submission is complete.

##### Call to undefined method React\Promise\Promise::flatMap()

Check that version of [Bunny](https://github.com/Domraider/bunny) is the 
latest (dev-master is the problem persist).

##### The class RabbitQueue lack of PHP documentation.

### Consumer
