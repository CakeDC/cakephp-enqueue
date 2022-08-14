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
use Cake\ORM\TableRegistry;
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

class CakeContext implements Context
{
    /**
     * @var \Cake\Database\Connection
     */
    private $connection;

    /**
     * @var callable
     */
    private $connectionFactory;

    /**
     * @var array
     */
    private $config;

    /**
     * Callable must return instance of Cake\Database\Connection once called.
     *
     * @param \Cake\Database\Connection|callable $connection Connection instance.
     * @param array $config Config settings.
     */
    public function __construct($connection, array $config = [])
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
                Connection::class
            );
            throw new \InvalidArgumentException($msg);
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
            $arrayMessage['headers'] ? JSON::decode($arrayMessage['headers']) : []
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
            ['queue' => $queue->getQueueName()]
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
        if ($this->connection == false) {
            $connection = call_user_func($this->connectionFactory);
            if ($connection instanceof ConnectionInterface == false) {
                $template = 'The factory must return instance of Cake\Datasource\ConnectionInterface. It returns %s';
                $msg = sprintf($template, is_object($connection) ? get_class($connection) : gettype($connection));
                throw new \LogicException($msg);
            }

            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * @return \Cake\ORM\Table
     */
    public function getTable()
    {
        $connection = $this->getCakeConnection();
        $table = TableRegistry::getTableLocator()->get('Cake/Enqueue.Enqueue');
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
        $connection = $this->getCakeConnection();
        $schema = $connection->getDriver()->newTableSchema($this->getTableName());

        if ($this->tableExists($this->getTableName())) {
            return;
        }

        $schema = $connection->getDriver()->newTableSchema($this->getTableName());

        $schema->addColumn('id', ['type' => 'uuid']);
        $schema->addColumn('published_at', ['type' => 'integer', 'length' => 11]);
        $schema->addColumn('body', ['type' => 'text', 'null' => true]);
        $schema->addColumn('headers', ['type' => 'text', 'null' => true]);
        $schema->addColumn('properties', ['type' => 'text', 'null' => true]);
        $schema->addColumn('redelivered', ['type' => 'boolean', 'null' => true]);
        $schema->addColumn('queue', ['type' => 'string']);
        $schema->addColumn('priority', ['type' => 'integer', 'length' => 5, 'null' => true]);
        $schema->addColumn('delayed_until', ['type' => 'integer', 'null' => true]);
        $schema->addColumn('time_to_live', ['type' => 'integer', 'null' => true]);
        $schema->addColumn('delivery_id', ['type' => 'uuid', 'null' => true]);
        $schema->addColumn('redeliver_after', ['type' => 'integer', 'null' => true]);

        $schema->addConstraint('primary', [
            'type' => 'primary',
            'columns' => ['id'],
        ]);
        $schema->addIndex('priority_idx', [
            'type' => 'index',
            'columns' => ['priority', 'published_at', 'queue', 'delivery_id', 'delayed_until', 'id'],
        ]);

        $schema->addIndex('redeliver_idx', [
            'type' => 'index',
            'columns' => ['redeliver_after', 'delivery_id'],
        ]);
        $schema->addIndex('ttl_idx', [
            'type' => 'index',
            'columns' => ['time_to_live', 'delivery_id'],
        ]);
        $schema->addIndex('delivery_id_idx', [
            'type' => 'index',
            'columns' => ['delivery_id'],
        ]);

        try {
            /** @psalm-suppress ArgumentTypeCoercion */
            $queries = $schema->createSql($connection);
            foreach ($queries as $query) {
                $stmt = $connection->prepare($query);
                $stmt->execute();
                $stmt->closeCursor();
            }
        } catch (\Exception $e) {
            $msg = sprintf(
                'Table creation for "%s" failed "%s"',
                $this->getTableName(),
                $e->getMessage()
            );
            trigger_error($msg, E_USER_WARNING);
        }
    }
}
