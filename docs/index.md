Home
====

The **CakePHP Enqueue** plugin provides message queue integration for CakePHP applications and uses database as a message broker.

Quick Start
-----------

1. Install the plugin:

```bash
   composer require cakedc/cakephp-enqueue
```

2. Load the plugin in your `Application.php`:

```php
   $this->addPlugin('CakephpEnqueue');
```

3. Configure your queue in `config/app.php`:

```php
   'Queue' => [
       'default' => [
           'url' => 'cakephp://default?table_name=queue'
       ]
   ]
```

4. Create a job:

```php
    use App\Job\ExampleJob;
    use Cake\Queue\QueueManager;

    $data = ['id' => 7, 'is_premium' => true];
    $options = ['config' => 'default'];

    QueueManager::push(ExampleJob::class, $data, $options);
```

5. Process jobs:

```bash
   bin/cake queue:worker
```

DSN Configuration
-----------------

The plugin supports standard DSN format:
- `cakephp://connection_name?table_name=queue&polling_interval=1000`
