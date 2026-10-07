Known Caveats
=============

Things that are easy to get wrong with this transport. Each item links to the page with the details.

Configuration
-------------

* `polling_interval` does not affect `bin/cake queue worker`, which uses `subscription_polling_interval` (200 ms). [Configuration](Configuration.md#options)
* With an array `url`, set the `queue` key (or a `client` key), otherwise the Enqueue client cannot be built. [Configuration](Configuration.md#array-configuration)
* With an array `transport`, the DSN host and query are ignored; set `connection` and `table_name` as keys. [Configuration](Configuration.md#array-configuration)
* Only `cakephp://` DSNs are accepted; `cakephpenqueue://` is registered but rejected by the factory. [Overview](Overview.md)
* `Cake/Queue` must find the `Queue` configuration when it is loaded. [Installation](Installation.md#configure-the-queue)

Queue names and routing
-----------------------

* Rows are stored under the prefixed queue name `enqueue.app.<queue>`, not under the `queue` option passed to `push()`. [Queue names](Usage.md#queue-names)
* A job pushed with a `queue` option different from the worker's queue is routed to nobody and dropped. [Queue names](Usage.md#queue-names)
* `purgeQueue($context->createQueue('default'))` removes nothing; build the destination through the driver. [Emptying a queue](Usage.md#emptying-a-queue)
* `delay`, `expires` and `priority` only take effect once a worker has routed the job. [Queue names](Usage.md#queue-names)

Processing
----------

* A job running longer than `redelivery_delay` (20 minutes by default) is delivered again to another worker. [Message Lifecycle](Message-Lifecycle.md#failed-and-rejected-messages)
* Without `$maxAttempts` or `--max-attempts` a failing job is requeued forever. [Message Lifecycle](Message-Lifecycle.md#failed-and-rejected-messages)
* `--max-jobs` is enforced per consume cycle, so a worker may process more jobs than the limit. [Process jobs](Usage.md#process-jobs)
* An expired message claimed before the housekeeping run is kept and delivered after `redelivery_delay`. [Time to live](Message-Lifecycle.md#time-to-live)

Database
--------

* Automatic table creation only supports MySQL/MariaDB, PostgreSQL and SQLite; other drivers get a warning. [Database Schema](Database-Schema.md)
* The table is created when the configuration is first used, so the database user needs `CREATE TABLE` then; the low level transport never creates it. [Installation](Installation.md#the-queue-table), [Enqueue Client](Enqueue-Client.md#low-level-transport)
* On SQLite a second queue table in the same database cannot be created automatically (index name collision). [Database Schema](Database-Schema.md#indexes)
* Creating the table also creates the migrations bookkeeping table (`cake_migrations`). [Database Schema](Database-Schema.md#indexes)
