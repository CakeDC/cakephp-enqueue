<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Enqueue;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionInterface;
use Cake\ORM\Table as OrmTable;
use Cake\ORM\TableRegistry;
use Exception;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Destination;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\TemporaryQueueNotSupportedException;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use Interop\Queue\Topic;
use InvalidArgumentException;
use LogicException;
use Migrations\Db\Adapter\MysqlAdapter;
use Migrations\Db\Adapter\PostgresAdapter;
use Migrations\Db\Adapter\SqliteAdapter;
use Migrations\Db\Table;
use RuntimeException;

class CakeContext implements Context
{
    /**
     * @var \Cake\Database\Connection|null
     */
    private ?Connection $connection = null;

    /**
     * @var callable
     */
    private $connectionFactory;

    /**
     * @var array
     */
    private array $config;

    /**
     * Callable must return instance of Cake\Database\Connection once called.
     *
     * @param \Cake\Database\Connection|callable $connection Connection instance.
     * @param array $config Config settings.
     */
    public function __construct(Connection|callable $connection, array $config = [])
    {
        $this->config = array_replace([
            'table_name' => 'enqueue',
            'polling_interval' => null,
            'subscription_polling_interval' => null,
        ], $config);

        if ($connection instanceof Connection) {
            $this->connection = $connection;
        } elseif (is_callable($connection)) {
            $this->connectionFactory = $connection;
        } else {
            $msg = sprintf(
                'The connection argument must be either %s or callable that returns %s.',
                Connection::class,
                Connection::class,
            );
            throw new InvalidArgumentException($msg);
        }
    }

    /**
     * @inheritDoc
     */
    public function createMessage(string $body = '', array $properties = [], array $headers = []): Message
    {
        $message = new CakeMessage();
        $message->setBody($body);
        $message->setProperties($properties);
        $message->setHeaders($headers);

        return $message;
    }

    /**
     * @param string $name Queue name.
     * @return \Cake\Enqueue\CakeDestination
     */
    public function createQueue(string $name): Queue
    {
        return new CakeDestination($name);
    }

    /**
     * @param string $name Topic name.
     * @return \Cake\Enqueue\CakeDestination
     */
    public function createTopic(string $name): Topic
    {
        return new CakeDestination($name);
    }

    /**
     * @return \Interop\Queue\Queue
     * @throws \Interop\Queue\Exception\TemporaryQueueNotSupportedException
     */
    public function createTemporaryQueue(): Queue
    {
        throw TemporaryQueueNotSupportedException::providerDoestNotSupportIt();
    }

    /**
     * @return \Cake\Enqueue\CakeProducer
     */
    public function createProducer(): Producer
    {
        return new CakeProducer($this);
    }

    /**
     * @param \Interop\Queue\Destination $destination Destination name.
     * @return \Cake\Enqueue\CakeConsumer
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     */
    public function createConsumer(Destination $destination): Consumer
    {
        InvalidDestinationException::assertDestinationInstanceOf($destination, CakeDestination::class);

        $consumer = new CakeConsumer($this, $destination);

        if (isset($this->config['polling_interval'])) {
            $consumer->setPollingInterval((int)$this->config['polling_interval']);
        }

        if (isset($this->config['redelivery_delay'])) {
            $consumer->setRedeliveryDelay((int)$this->config['redelivery_delay']);
        }

        return $consumer;
    }

    /**
     * @return void
     */
    public function close(): void
    {
    }

    /**
     * @return \Interop\Queue\SubscriptionConsumer
     */
    public function createSubscriptionConsumer(): SubscriptionConsumer
    {
        $consumer = new CakeSubscriptionConsumer($this);

        if (isset($this->config['redelivery_delay'])) {
            $consumer->setRedeliveryDelay($this->config['redelivery_delay']);
        }

        if (isset($this->config['subscription_polling_interval'])) {
            $consumer->setPollingInterval($this->config['subscription_polling_interval']);
        }

        return $consumer;
    }

    /**
     * @param array $arrayMessage Message array.
     * @return \Cake\Enqueue\CakeMessage
     * @internal It must be used here and in the consumer only
     */
    public function convertMessage(array $arrayMessage): CakeMessage
    {
        /** @var \Cake\Enqueue\CakeMessage $message */
        $message = $this->createMessage(
            $arrayMessage['body'],
            $arrayMessage['properties'] ? JSON::decode($arrayMessage['properties']) : [],
            $arrayMessage['headers'] ? JSON::decode($arrayMessage['headers']) : [],
        );

        if (isset($arrayMessage['id'])) {
            $message->setMessageId($arrayMessage['id']);
        }
        if (isset($arrayMessage['queue'])) {
            $message->setQueue($arrayMessage['queue']);
        }
        if (isset($arrayMessage['redelivered'])) {
            $message->setRedelivered((bool)$arrayMessage['redelivered']);
        }
        if (isset($arrayMessage['priority'])) {
            $message->setPriority((int)(-1 * $arrayMessage['priority']));
        }
        if (isset($arrayMessage['published_at'])) {
            $message->setPublishedAt((int)$arrayMessage['published_at']);
        }
        if (isset($arrayMessage['delivery_id'])) {
            $message->setDeliveryId($arrayMessage['delivery_id']);
        }
        if (isset($arrayMessage['redeliver_after'])) {
            $message->setRedeliverAfter((int)$arrayMessage['redeliver_after']);
        }

        return $message;
    }

    /**
     * @param \Cake\Enqueue\CakeDestination $queue Queue name.
     * @return void
     */
    public function purgeQueue(Queue $queue): void
    {
        $this->getCakeConnection()->delete(
            $this->getTableName(),
            ['queue' => $queue->getQueueName()],
        );
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->config['table_name'];
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return \Cake\Database\Connection
     */
    public function getCakeConnection(): Connection
    {
        if ($this->connection == null) {
            $connection = call_user_func($this->connectionFactory);
            if ($connection instanceof ConnectionInterface == false) {
                $template = 'The factory must return instance of Cake\Datasource\ConnectionInterface. It returns %s';
                $msg = sprintf($template, is_object($connection) ? get_class($connection) : gettype($connection));
                throw new LogicException($msg);
            }

            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * @return \Cake\ORM\Table
     */
    public function getTable(): OrmTable
    {
        $table = TableRegistry::getTableLocator()->get('Cake/Enqueue.Enqueue', [
            'connection' => $this->getCakeConnection(),
        ]);
        $table->setTable($this->getTableName());

        return $table;
    }

    /**
     * @param string $tableName Table name.
     * @return bool
     */
    protected function tableExists(string $tableName): bool
    {
        $connection = $this->getCakeConnection();
        $collection = $connection->getSchemaCollection();
        $tables = $collection->listTables();

        return in_array($tableName, (array)$tables);
    }

    /**
     * @return void
     */
    public function createDataBaseTable(): void
    {
        if ($this->tableExists($this->getTableName())) {
            return;
        }

        try {
            $connection = $this->getCakeConnection();

            $config = $connection->config();
            $driverClass = get_class($connection->getDriver());
            if (strpos($driverClass, 'Sqlite') !== false) {
                $adapterConfig = array_merge($config, [
                    'adapter' => 'sqlite',
                    'connection' => $connection,
                    'driver' => 'sqlite',
                ]);
                $adapter = new SqliteAdapter($adapterConfig);
            } elseif (strpos($driverClass, 'Mysql') !== false) {
                $adapterConfig = array_merge($config, [
                    'adapter' => 'mysql',
                    'connection' => $connection,
                    'driver' => 'mysql',
                ]);
                $adapter = new MysqlAdapter($adapterConfig);
            } elseif (strpos($driverClass, 'Postgres') !== false) {
                $adapterConfig = array_merge($config, [
                    'adapter' => 'pgsql',
                    'connection' => $connection,
                    'driver' => 'pgsql',
                ]);
                $adapter = new PostgresAdapter($adapterConfig);
            } else {
                throw new RuntimeException('Unsupported database driver: ' . $driverClass);
            }

            $table = new Table($this->getTableName(), ['id' => false], $adapter);

            $table->addColumn('id', 'uuid');
            $table->addColumn('published_at', 'biginteger');
            $table->addColumn('body', 'text', ['null' => true]);
            $table->addColumn('headers', 'text', ['null' => true]);
            $table->addColumn('properties', 'text', ['null' => true]);
            $table->addColumn('redelivered', 'boolean', ['null' => true]);
            $table->addColumn('queue', 'string');
            $table->addColumn('priority', 'integer', ['limit' => 5, 'null' => true]);
            $table->addColumn('delayed_until', 'biginteger', ['null' => true]);
            $table->addColumn('time_to_live', 'biginteger', ['null' => true]);
            $table->addColumn('delivery_id', 'uuid', ['null' => true]);
            $table->addColumn('redeliver_after', 'biginteger', ['null' => true]);

            $table->addPrimaryKey(['id']);

            $table->addIndex([
                'priority',
                'published_at',
                'queue',
                'delivery_id',
                'delayed_until',
                'id',
            ], [
                'name' => 'priority_idx',
            ]);
            $table->addIndex(['redeliver_after', 'delivery_id'], ['name' => 'redeliver_idx']);
            $table->addIndex(['time_to_live', 'delivery_id'], ['name' => 'ttl_idx']);
            $table->addIndex(['delivery_id'], ['name' => 'delivery_id_idx']);

            $table->create();
        } catch (Exception $e) {
            $msg = sprintf(
                'Table creation for "%s" failed "%s"',
                $this->getTableName(),
                $e->getMessage(),
            );
            trigger_error($msg, E_USER_WARNING);
        }
    }
}
