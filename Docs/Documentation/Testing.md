Testing
=======

Running the plugin tests
------------------------

```
composer install
composer test
```

By default the tests run against an in-memory SQLite database. To use another database set the `db_dsn` environment variable before running PHPUnit:

```
export db_dsn='mysql://root:root@127.0.0.1/cakephp'
vendor/bin/phpunit
```

```
export db_dsn='postgres://postgres:postgres@127.0.0.1/cakephp'
vendor/bin/phpunit
```

The GitHub workflow runs the suite on PHP 8.2, 8.3 and 8.4 with a SQLite, MySQL and PostgreSQL matrix.

Coding standards
----------------

```
composer cs-check
composer cs-fix
```

Testing your application jobs
-----------------------------

To assert that jobs were queued without touching the database, use the `QueueTrait` shipped with `cakephp/queue`, which swaps every queue client for an in-memory one:

```php
use App\Job\InvoiceJob;
use Cake\Queue\QueueManager;
use Cake\Queue\TestSuite\QueueTrait;
use Cake\TestSuite\TestCase;

class OrdersControllerTest extends TestCase
{
    use QueueTrait;

    public function testOrderQueuesJob(): void
    {
        QueueManager::push(InvoiceJob::class, ['id' => 1]);

        $this->assertJobQueued(InvoiceJob::class);
        $this->assertJobQueuedWith(InvoiceJob::class, ['id' => 1]);
    }
}
```

To run the real transport in tests point a configuration at the `test` connection; the table is created on first use:

```php
use Cake\Queue\QueueManager;

QueueManager::setConfig('default', [
    'url' => 'cakephp://test',
    'queue' => 'default',
]);
```

To clean the table between tests, purge the prefixed queue (see [Emptying a queue](Usage.md#emptying-a-queue)) or truncate the `enqueue` table. `QueueManager::drop('default')` discards the client so that the next test builds a fresh one.

NOTE: `QueueManager::setConfig()` throws `Cannot reconfigure existing key` if the key is already configured; drop it first in `tearDown()`.
