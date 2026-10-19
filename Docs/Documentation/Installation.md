Installation
============

Composer
--------

```
composer require cakedc/cakephp-enqueue
```

This also installs `cakephp/queue` and `cakephp/migrations`. The plugin uses the `cakephp/migrations` database adapters to create the queue table.

Configure the queue
-------------------

Add a queue configuration that uses the `cakephp://` DSN to your `config/app.php`:

```php
    'Queue' => [
        'default' => [
            'url' => 'cakephp://default?table_name=enqueue',
            'queue' => 'default',
        ],
    ],
```

`default` in the DSN is the name of the CakePHP datasource connection to use. See [Configuration](Configuration.md) for all the available options.

NOTE: do this before loading the plugins. `Cake/Queue` throws `Missing 'Queue' configuration key` from its bootstrap if the `Queue` key is not set.

Load the Plugin
---------------

Ensure the plugin is loaded in your `src/Application.php` file, together with `Cake/Queue`:

```php
    /**
     * {@inheritdoc}
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Cake/Enqueue');
        $this->addPlugin('Cake/Queue');
    }
```

Or using the CLI:

```
bin/cake plugin load Cake/Enqueue
bin/cake plugin load Cake/Queue
```

The plugin name is `Cake/Enqueue` (from the `Cake\Enqueue` namespace), not the composer package name.

The queue table
---------------

You don't need to write a migration. The table is created the first time a queue configuration is used, that is on the first `QueueManager::push()` or when the worker starts, through the driver's `setupBroker()`. If the table already exists nothing happens.

NOTE: automatic creation works on MySQL/MariaDB, PostgreSQL and SQLite. The database user needs the `CREATE TABLE` privilege the first time. If creation fails (other drivers, missing privilege) a `E_USER_WARNING` with the reason is triggered and the first query against the table fails. See [Database Schema](Database-Schema.md) to create it yourself.

Run a worker
------------

```
bin/cake queue worker
```

Continue with [Usage](Usage.md) to send your first job.

Failed jobs storage (optional)
------------------------------

Failed jobs are not kept in the queue table. If you want `cakephp/queue` to store them, set `'storeFailedJobs' => true` in the queue configuration and create its `queue_failed_jobs` table:

```
bin/cake migrations migrate --plugin Cake/Queue
```

The `queue requeue` and `queue purge_failed` commands work with that table, see the [cakephp/queue book](https://book.cakephp.org/queue/2/).
