Database Schema
===============

The table is created by `CakeContext::createDataBaseTable()`, which the client driver calls from `setupBroker()` the first time a queue configuration is used (first `QueueManager::push()` or worker start). The name comes from the `table_name` option (default `enqueue`). The same structure is created on MySQL/MariaDB, PostgreSQL and SQLite using the `cakephp/migrations` adapters; any other driver triggers a warning and the table must be created by hand.

Columns
-------

| Column | Type | Null | Description |
| :----- | :--- | :--- | :---------- |
| id | uuid | no | Primary key. |
| published_at | biginteger | no | Publish time as `microtime(true) * 10000` (tenths of a millisecond). Used for ordering. |
| body | text | yes | Message body. |
| headers | text | yes | JSON encoded headers. |
| properties | text | yes | JSON encoded properties. `cakephp/queue` keeps the topic, delay, expire and priority here. |
| redelivered | boolean | yes | `true` after the claim of an unacknowledged message was released. |
| queue | string | no | Queue name, prefixed by the Enqueue client, for example `enqueue.app.default`. |
| priority | integer(5) | yes | Stored negated, so the default ascending order delivers the highest priority first. |
| delayed_until | biginteger | yes | Unix time before which the message is not delivered. |
| time_to_live | biginteger | yes | Unix time after which an unclaimed message is removed. |
| delivery_id | uuid | yes | Set while a consumer holds the message. |
| redeliver_after | biginteger | yes | Unix time after which a claimed message is made available again. |

Indexes
-------

| Name | Columns |
| :--- | :------ |
| priority_idx | priority, published_at, queue, delivery_id, delayed_until, id |
| redeliver_idx | redeliver_after, delivery_id |
| ttl_idx | time_to_live, delivery_id |
| delivery_id_idx | delivery_id |

NOTE: SQLite index names are global per database. Automatic creation of a second queue table in the same SQLite database fails on `priority_idx already exists` with a warning, leaving the second table without indexes. Use one table per SQLite database, or create the second table yourself with other index names.

NOTE: the migrations adapter also creates its own bookkeeping table (`cake_migrations` with `cakephp/migrations` 5) in the queue connection if it does not exist yet.

Creating the table yourself
---------------------------

To create the table outside of the worker, for example while deploying, either trigger the normal setup:

```php
use Cake\Queue\QueueManager;

QueueManager::engine('default'); // calls setupBroker(), creates the table if missing
```

or use the transport directly:

```php
use Cake\Enqueue\CakeConnectionFactory;

$context = (new CakeConnectionFactory('cakephp://default?table_name=enqueue'))->createContext();
$context->createDataBaseTable(); // does nothing if the table exists
```

If you prefer a migration in your application, this one produces the same structure:

```php
use Migrations\BaseMigration;

class CreateEnqueue extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('enqueue', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('published_at', 'biginteger')
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('headers', 'text', ['null' => true])
            ->addColumn('properties', 'text', ['null' => true])
            ->addColumn('redelivered', 'boolean', ['null' => true])
            ->addColumn('queue', 'string')
            ->addColumn('priority', 'integer', ['limit' => 5, 'null' => true])
            ->addColumn('delayed_until', 'biginteger', ['null' => true])
            ->addColumn('time_to_live', 'biginteger', ['null' => true])
            ->addColumn('delivery_id', 'uuid', ['null' => true])
            ->addColumn('redeliver_after', 'biginteger', ['null' => true])
            ->addIndex(['priority', 'published_at', 'queue', 'delivery_id', 'delayed_until', 'id'], ['name' => 'priority_idx'])
            ->addIndex(['redeliver_after', 'delivery_id'], ['name' => 'redeliver_idx'])
            ->addIndex(['time_to_live', 'delivery_id'], ['name' => 'ttl_idx'])
            ->addIndex(['delivery_id'], ['name' => 'delivery_id_idx'])
            ->create();
    }
}
```

Reading messages
----------------

The `Cake/Enqueue.Enqueue` table class can be used to inspect the queue, for example to build a monitoring page. Remember that queue names are prefixed:

```php
$table = $this->fetchTable('Cake/Enqueue.Enqueue');

$pending = $table->find()
    ->where(['queue' => 'enqueue.app.default', 'delivery_id IS' => null])
    ->count();

$table->getSize('enqueue.app.default'); // rows in that queue, claimed ones included
$table->getAllSizes();                   // [['queue' => 'enqueue.app.default', 'job_count' => 3], ...]
```

If `table_name` is not `enqueue`, call `$table->setTable('my_queue_table')` first, or get the configured instance from the context: `QueueManager::engine('default')->getDriver()->getContext()->getTable()`.

NOTE: Do not modify rows directly while workers are running. Use the producer and consumer API instead.
