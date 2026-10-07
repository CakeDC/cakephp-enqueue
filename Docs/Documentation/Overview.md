Overview
========

The plugin registers a `cakephp` transport (connection factory) and a client driver with Enqueue. Anything built on Enqueue, including `cakephp/queue`, can then use a CakePHP database connection as its message broker.

The plugin is capable of:

* Storing messages in a single database table, using the CakePHP ORM
* Creating the queue table automatically on MySQL/MariaDB, PostgreSQL and SQLite
* Multiple named queues in the same table
* Message priorities
* Delayed delivery (`delay`)
* Message expiration (`expires` / time to live)
* Automatic redelivery of messages that were received but never acknowledged
* Rejecting messages, with or without requeueing
* Lazy connections, so the database is only touched when a message is sent or received
* Running side by side with other Enqueue transports (Redis, filesystem, ...) in different queue configurations

What it is not:

* A full broker. Consumers poll the table; there is no push notification, no fan-out and no temporary queues (`createTemporaryQueue()` throws `TemporaryQueueNotSupportedException`).
* A migration. The table is created on the fly by the transport, see [Database Schema](Database-Schema.md).

Requirements
------------

| Plugin | CakePHP | PHP    | cakephp/queue | cakephp/migrations |
| :----: | :-----: | :----: | :-----------: | :----------------: |
| 2.x    | ^5.1    | >= 8.2 | ^2.0          | ^4.0 or ^5.0       |
| 1.x    | ^4.3    | >= 7.2 | any           | not used           |

Classes
-------

| Class | Responsibility |
| :---- | :------------- |
| `Cake\Enqueue\EnqueuePlugin` | Registers the transport and the client driver with Enqueue |
| `Cake\Enqueue\CakeConnectionFactory` | Parses the DSN or config array and creates the context |
| `Cake\Enqueue\CakeContext` | Creates queues, producers, consumers and the queue table |
| `Cake\Enqueue\CakeProducer` | Stores messages in the table |
| `Cake\Enqueue\CakeConsumer` | Fetches, acknowledges and rejects messages of one queue |
| `Cake\Enqueue\CakeSubscriptionConsumer` | Consumes from several queues at once, used by the worker |
| `Cake\Enqueue\CakeMessage` | Transport message (body, headers, properties and delivery data) |
| `Cake\Enqueue\CakeDestination` | Queue / topic name |
| `Cake\Enqueue\Client\Driver\CakephpDriver` | Enqueue client driver, creates the table in `setupBroker()` |
| `Cake\Enqueue\Model\Table\EnqueueTable` | ORM table used to read and write messages |

NOTE: the transport is registered under the schemes `cakephp` and `cakephpenqueue`, but the connection factory only accepts `cakephp://` DSNs. A `cakephpenqueue://` DSN throws `Wrong dsn schema passed`.
