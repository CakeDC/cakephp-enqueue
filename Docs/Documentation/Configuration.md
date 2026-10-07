Configuration
=============

The transport is configured through the `url` of a `cakephp/queue` configuration, either as a DSN string or as an array.

DSN
---

```
cakephp://<connection>?table_name=<table>&polling_interval=<ms>&lazy=<bool>
```

```php
// config/app.php
'Queue' => [
    'default' => [
        'url' => 'cakephp://default?table_name=enqueue',
        'queue' => 'default',
    ],
    'reports' => [
        'url' => 'cakephp://default?table_name=enqueue&lazy=false',
        'queue' => 'reports',
    ],
],
```

The host part is the name of the CakePHP datasource connection (`ConnectionManager::get()`); when it is empty `default` is used. Only the `cakephp` scheme is accepted, any other scheme throws `Wrong dsn schema passed`.

Options
-------

| Option | Type | Default | Description |
| :----- | :--- | :------ | :---------- |
| connection | string | `default` | Name of the CakePHP datasource connection. In a DSN it is the host part. |
| table_name | string | `enqueue` | Table where messages are stored. |
| lazy | bool | `true` | Connect to the database only when it is first needed. |
| polling_interval | int (ms) | `1000` | Sleep between polls of `CakeConsumer::receive()`. Not used by the worker, see below. |
| subscription_polling_interval | int (ms) | `200` | Sleep between polls of the subscription consumer, which is what `bin/cake queue worker` uses when the queue is empty. |
| redelivery_delay | int (ms) | `1200000` (20 minutes) | Time a received message can stay unacknowledged before it is redelivered to another consumer. Values that are not a multiple of 1000 are truncated to whole seconds. |

NOTE: from a DSN, `polling_interval` and `lazy` are cast when parsed; `redelivery_delay` and `subscription_polling_interval` stay strings and are cast to integers when the consumers are created, so all options can be set in the DSN.

Array configuration
-------------------

`cakephp/queue` also accepts an array as `url`. The `transport` key can be an array with the options above:

```php
'Queue' => [
    'default' => [
        'url' => [
            'transport' => [
                'dsn' => 'cakephp:',
                'connection' => 'default',
                'table_name' => 'enqueue',
                'redelivery_delay' => 600000,
                'subscription_polling_interval' => 500,
            ],
        ],
        'queue' => 'default',
    ],
],
```

NOTE: when `transport` is an array with keys other than `dsn`, the whole array is passed to `CakeConnectionFactory` and the DSN is only used to select the transport: its host and query string are ignored. Set `connection` and `table_name` as keys.

NOTE: with the array form, `cakephp/queue` only fills in the Enqueue `client` section when the `queue` key is set. Without `queue` you must add `'client' => true` (or an array of client options) next to `transport`, otherwise `SimpleClient` fails with a `TypeError` on `Config::__construct()`.

If you use the transport classes directly, `CakeConnectionFactory` accepts the same options array or a DSN string:

```php
use Cake\Enqueue\CakeConnectionFactory;

$factory = new CakeConnectionFactory([
    'connection' => 'default',
    'table_name' => 'enqueue',
    'redelivery_delay' => 600000,
]);
```

Dedicated connection
--------------------

Because the queue table is read and written constantly, you may want it in its own database. Define an additional datasource and point the DSN to it:

```php
// config/app_local.php
'Datasources' => [
    'default' => [ /* ... */ ],
    'queue' => [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'host' => 'localhost',
        'database' => 'my_app_queue',
    ],
],
```

```php
// config/app.php
'Queue' => [
    'default' => [
        'url' => 'cakephp://queue?table_name=enqueue',
        'queue' => 'default',
    ],
],
```

With a dedicated connection the message insert is not part of transactions opened on your application connection. Sharing the connection means a rollback also removes the queued message, which may or may not be what you want.

Several queue configurations
----------------------------

Different configurations can share the same table as long as they use different `queue` names: each one stores its messages under its own queue (prefixed, see [Queue names](Usage.md#queue-names)). They can also use different tables or connections. One worker process serves one configuration, start one `bin/cake queue worker --config=<name>` per configuration.

Plugin name
-----------

The plugin name is `Cake/Enqueue`, derived from its namespace, not the composer package name. Use it in `addPlugin()` and in table registry aliases, for example `Cake/Enqueue.Enqueue`.
