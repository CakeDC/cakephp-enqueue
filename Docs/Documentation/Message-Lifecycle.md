Message Lifecycle
=================

Sending
-------

`CakeProducer::send()` inserts one row in the queue table with a generated UUID, the body, JSON encoded headers and properties, the queue name, the negated priority and `published_at` (`microtime(true) * 10000`). No `delivery_id` is set, which marks the message as available.

With `cakephp/queue` a job is written twice: first to the router queue by `QueueManager::push()`, then again to the processor queue by the worker's router processor, which acknowledges (deletes) the router row. Both rows use the same queue name by default, see [Queue names](Usage.md#queue-names).

Receiving
---------

A consumer selects the first message that:

* belongs to the requested queue(s)
* has no `delivery_id`, meaning nobody is processing it
* is not delayed, or its delay has already passed

Messages are ordered by priority first and then by `published_at`. The consumer claims the message by writing a new `delivery_id` and `redeliver_after = now + redelivery_delay` with an `UPDATE ... WHERE delivery_id IS NULL`, so two workers never receive the same message. If another worker won the race the consumer retries for up to 200 ms before giving up for this poll.

When the queue is empty:

* `bin/cake queue worker` (through `CakeSubscriptionConsumer`) sleeps `subscription_polling_interval` ms (200 by default) and polls again until `receiveTimeout` ms (10000 by default) have passed.
* `CakeConsumer::receive($timeout)` sleeps `polling_interval` ms (1000 by default) between polls.

Acknowledging
-------------

When a job returns `Processor::ACK` (or `null`) the row is deleted.

Failed and rejected messages
----------------------------

* `Processor::REJECT` deletes the row.
* `Processor::REQUEUE`, or an exception thrown by the job, inserts a copy of the message as a new available row (new id, new `published_at`, `redelivered = false`) and deletes the original. With `cakephp/queue` the copy carries an incremented `attempts` property; once `attempts` reaches `$maxAttempts` (or `--max-attempts`) the job is rejected instead and, if `storeFailedJobs` is enabled, stored in `queue_failed_jobs`. Without a limit a failing job is requeued forever.
* If the worker dies, or never acknowledges the message, the row stays claimed until `redeliver_after` is reached (`redelivery_delay`, 20 minutes by default). Then the claim is cleared and the row is marked `redelivered = true` so another worker can take it. The job sees `$message->getOriginalMessage()->isRedelivered() === true`.

NOTE: redelivery is time based. A job that runs longer than `redelivery_delay` is handed to a second worker while the first one is still running it. Raise `redelivery_delay` above your longest job, see [Configuration](Configuration.md#options).

Delayed messages
----------------

`delay` is given in seconds to `QueueManager::push()` and converted to milliseconds by the Enqueue client. The transport stores `delayed_until = time() + delay` (whole seconds, sub-second delays are truncated) and consumers skip the row until then. The delay must be a positive integer, otherwise `CakeProducer` throws a `LogicException`.

With `cakephp/queue` the delay is applied when the router processor re-sends the message, so it starts counting when a worker routes it, not when it is pushed.

Time to live
------------

`expires` is given in seconds and stored as `time_to_live = time() + expires` on the processor row. Before each poll, consumers delete the rows whose `time_to_live` has passed and that are neither claimed nor redelivered.

NOTE: the fetch query does not filter by `time_to_live`. If a row expires between the housekeeping run and the claim, the consumer claims it, skips it and leaves it claimed; after `redelivery_delay` it is redelivered with `redelivered = true` and, because redelivered rows are never expired, it is then delivered normally.

Housekeeping
------------

Redelivery and expiration checks run inside every consumer, before each poll and at most once per second, over the whole table (all queues), so no cron job is needed. Errors in these queries are ignored and retried on the next poll.
