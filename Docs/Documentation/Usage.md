Usage
=====

The plugin does not add its own API. You use the `cakephp/queue` package as usual and the plugin acts as the broker. See the [cakephp/queue book](https://book.cakephp.org/queue/2/) for everything about jobs.

Create a job
------------

```php
// src/Job/ExampleJob.php
namespace App\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class ExampleJob implements JobInterface
{
    public static ?int $maxAttempts = 3;

    public function execute(Message $message): ?string
    {
        $id = $message->getArgument('id');

        // do the work...

        return Processor::ACK;
    }
}
```

Returning `null` is the same as `Processor::ACK`. Returning `Processor::REQUEUE`, or throwing, sends the job back to the queue; `Processor::REJECT` drops it. See [Message Lifecycle](Message-Lifecycle.md).

Push a job
----------

```php
use App\Job\ExampleJob;
use Cake\Queue\QueueManager;

$data = ['id' => 7, 'is_premium' => true];
$options = ['config' => 'default'];

QueueManager::push(ExampleJob::class, $data, $options);
```

Options
-------

`QueueManager::push()` accepts these options:

| Option | Description |
| :----- | :---------- |
| config | Name of the queue configuration to use. Defaults to `default`. |
| queue | Topic the job is published on. Defaults to the `queue` key of the configuration, or `default`. It must match the worker's queue, see [Queue names](#queue-names). |
| delay | Integer seconds to wait before the job becomes available. See [Delayed messages](Message-Lifecycle.md#delayed-messages). |
| expires | Integer seconds after which an unconsumed job is removed. See [Time to live](Message-Lifecycle.md#time-to-live). |
| priority | One of the `Enqueue\Client\MessagePriority` constants: `VERY_LOW`, `LOW`, `NORMAL`, `HIGH`, `VERY_HIGH` (stored as 0 to 4). Higher priority is delivered first. |

```php
use Enqueue\Client\MessagePriority;

QueueManager::push(ExampleJob::class, $data, [
    'config' => 'default',
    'delay' => 60,
    'expires' => 3600,
    'priority' => MessagePriority::HIGH,
]);
```

Queue names
-----------

`cakephp/queue` publishes jobs as Enqueue *events*: the job is stored in the configuration's router queue with the `queue` option as its topic, and the worker's router re-sends it to the processor queue, which is the same queue by default. Enqueue prefixes queue names with `enqueue.app.`, so a configuration with `'queue' => 'default'` stores all its rows with `queue = 'enqueue.app.default'` in the table, whatever the `queue` option of `push()` was.

Consequences:

* The `queue` push option is a topic name. A worker only processes jobs whose topic matches its `--queue` (defaulting to the configuration's `queue`). Jobs with another topic are routed to zero subscribers and silently acknowledged, that is, dropped. Keep the push option and the worker queue equal, or use one configuration per queue.
* `delay`, `expires` and `priority` are stored as message properties on the router row and only applied (as `delayed_until`, `time_to_live` and `priority` columns) when the worker routes the job to the processor queue. The delay starts counting at that moment, which requires a running worker.
* Anything that addresses the table by queue name, such as `purgeQueue()`, must use the prefixed name. Use the driver to build it, see [Emptying a queue](#emptying-a-queue).

Process jobs
------------

```
bin/cake queue worker
```

Options: `--config` (`-c`), `--queue` (`-Q`), `--processor` (`-p`), `--logger` (`-l`, only used together with `--verbose`), `--max-jobs` (`-i`), `--max-runtime` (`-r`, seconds) and `--max-attempts` (`-a`). A `$maxAttempts` property on a job overrides `--max-attempts`.

```
bin/cake queue worker --config=default --max-jobs=100 --max-runtime=3600 --max-attempts=3 --verbose
```

Run as many workers as you need; each message is claimed by a single worker. See [Message Lifecycle](Message-Lifecycle.md).

NOTE: `--max-jobs` is checked between consume cycles, and one cycle lasts `receiveTimeout` milliseconds (10000 by default, configurable per queue configuration). With this transport every job that becomes available during a cycle is processed, so a worker can process more than `--max-jobs` jobs before it stops.

NOTE: In production keep the worker alive with a process manager such as supervisor or systemd, and restart it on deployments so it picks up new code.

Emptying a queue
----------------

To remove every row of a configuration's queue, build the destination through the driver so the prefixed name is used:

```php
use Cake\Queue\QueueManager;

$driver = QueueManager::engine('default')->getDriver();
$queue = $driver->createQueue($driver->getConfig()->getRouterQueue());
$driver->getContext()->purgeQueue($queue);
```

`$context->purgeQueue($context->createQueue('default'))` does nothing, because no row has `queue = 'default'`; the rows are stored as `enqueue.app.default`. `purgeQueue()` deletes claimed and delayed rows too.
