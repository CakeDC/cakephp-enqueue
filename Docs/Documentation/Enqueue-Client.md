Using the Enqueue Client
========================

The plugin also registers a driver for the Enqueue client, so you can use `enqueue/simple-client` with the database transport without `cakephp/queue`. The plugin must be loaded (its `bootstrap()` registers the transport), the client is a separate package:

```
composer require enqueue/simple-client
```

```php
use Enqueue\SimpleClient\SimpleClient;
use Interop\Queue\Message;
use Interop\Queue\Processor;

$client = new SimpleClient('cakephp://default?table_name=enqueue');

$client->bindTopic('orders', function (Message $message) {
    // process $message->getBody()

    return Processor::ACK;
});

$client->setupBroker();      // creates the table if missing
$client->sendEvent('orders', 'order #1');
$client->consume();
```

`cakephp/queue` wraps this same client: `QueueManager::engine('default')` returns a `SimpleClient` built from your `Queue` configuration, with the client's `router_topic`, `router_queue` and `default_queue` set to the configuration's `queue`.

NOTE: the client routes each event through its router queue: `sendEvent()` stores one row in the router queue, the consumer's router processor then inserts the routed copy in the processor queue and deletes the router row. Both queues are `enqueue.app.default` by default (prefix `enqueue`, app name `app`, queue `default`). A consumer therefore handles two messages per event; keep that in mind when limiting consumption, for example with `--max-jobs` or `LimitConsumedMessagesExtension`.

Low level transport
-------------------

You can also work with the `queue-interop` API directly. Nothing creates the table for you at this level, call `createDataBaseTable()` once:

```php
use Cake\Enqueue\CakeConnectionFactory;

$factory = new CakeConnectionFactory('cakephp://default?table_name=enqueue');
$context = $factory->createContext();
$context->createDataBaseTable();

$queue = $context->createQueue('emails');

$producer = $context->createProducer();
$producer->setDeliveryDelay(5000);  // milliseconds, optional
$producer->setTimeToLive(60000);    // milliseconds, optional
$producer->setPriority(3);          // any integer, optional
$producer->send($queue, $context->createMessage('hello', ['key' => 'value']));

$consumer = $context->createConsumer($queue);
if ($message = $consumer->receive(2000)) { // wait up to 2000 ms
    // ...
    $consumer->acknowledge($message);
    // or $consumer->reject($message);        // drop
    // or $consumer->reject($message, true);  // requeue as a new row
}
```

At this level queue names are used as given (no `enqueue.app.` prefix), delays and TTLs are milliseconds (truncated to whole seconds when stored) and priorities are plain integers, higher first.

To consume several queues in one loop use the subscription consumer:

```php
$subscription = $context->createSubscriptionConsumer();
$subscription->subscribe($context->createConsumer($context->createQueue('emails')), function ($message, $consumer) {
    $consumer->acknowledge($message);

    return true; // return false to stop consuming
});
$subscription->subscribe($context->createConsumer($context->createQueue('reports')), function ($message, $consumer) {
    $consumer->acknowledge($message);

    return true;
});
$subscription->consume(10000); // run for 10000 ms, 0 = forever
```

The subscription consumer polls every `subscription_polling_interval` ms (200 by default) when all queues are empty, see [Configuration](Configuration.md#options). Only one consumer can be subscribed per queue name.
