Home
====

The **CakePHP Enqueue** plugin is a database transport for [cakephp/queue](https://github.com/cakephp/queue). It implements the [Enqueue](https://github.com/php-enqueue/enqueue) `queue-interop` transport on top of a CakePHP database connection, so you can run background jobs without installing a dedicated message broker.

That it works out of the box doesn't mean it is meant to replace a full broker in every scenario. It is a good fit when you already have a database and want simple, reliable job processing with zero extra infrastructure.

Documentation
-------------

* [Overview](Documentation/Overview.md)
* [Installation](Documentation/Installation.md)
* [Configuration](Documentation/Configuration.md)
* [Usage](Documentation/Usage.md)
* [Message Lifecycle](Documentation/Message-Lifecycle.md)
* [Database Schema](Documentation/Database-Schema.md)
* [Using the Enqueue Client](Documentation/Enqueue-Client.md)
* [Testing](Documentation/Testing.md)
* [Known Caveats](Documentation/Caveats.md)

I want to
---------

* get started quickly
  * <details>
      <summary>install and send my first job</summary>

      ```
      composer require cakedc/cakephp-enqueue
      ```

      ```php
      // config/app.php
      'Queue' => [
          'default' => [
              'url' => 'cakephp://default?table_name=enqueue',
              'queue' => 'default',
          ],
      ],
      ```

      ```php
      // src/Application.php
      $this->addPlugin('Cake/Enqueue');
      $this->addPlugin('Cake/Queue');
      ```

      ```php
      QueueManager::push(ExampleJob::class, ['id' => 7]);
      ```

      ```
      bin/cake queue worker
      ```

      See [Installation](Documentation/Installation.md) and [Usage](Documentation/Usage.md) for the details.
    </details>
* configure
  * <details>
      <summary>a different database connection or table</summary>

      ```php
      'url' => 'cakephp://my_connection?table_name=my_queue_table',
      ```
    </details>
  * <details>
      <summary>how often the worker polls the database</summary>

      ```php
      'url' => [
          'transport' => [
              'dsn' => 'cakephp:',
              'connection' => 'default',
              'subscription_polling_interval' => 500,
          ],
      ],
      'queue' => 'default',
      ```

      NOTE: the worker uses `subscription_polling_interval`, not `polling_interval`, and it must be an integer, so it can only be set with the array form. See [Configuration](Documentation/Configuration.md#options).
    </details>
  * <details>
      <summary>how long before an unacknowledged message is redelivered</summary>

      ```php
      'url' => [
          'transport' => [
              'dsn' => 'cakephp:',
              'connection' => 'default',
              'redelivery_delay' => 600000,
          ],
      ],
      'queue' => 'default',
      ```

      NOTE: milliseconds, truncated to whole seconds. See [Configuration](Documentation/Configuration.md#options).
    </details>
* understand
  * [where my job is stored and why the `queue` column says `enqueue.app.default`](Documentation/Usage.md#queue-names)
  * [what happens to a message that fails](Documentation/Message-Lifecycle.md#failed-and-rejected-messages)
  * [how delayed messages work](Documentation/Message-Lifecycle.md#delayed-messages)
  * [how expired messages are removed](Documentation/Message-Lifecycle.md#time-to-live)
  * [which table columns are used](Documentation/Database-Schema.md)
  * [what can go wrong](Documentation/Caveats.md)
