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
use Interop\Queue\Consumer;
use Interop\Queue\SubscriptionConsumer;
use InvalidArgumentException;
use LogicException;

class CakeSubscriptionConsumer implements SubscriptionConsumer
{
    use CakeConsumerHelperTrait;

    /**
     * @var \Cake\Enqueue\CakeContext
     */
    private CakeContext $context;

    /**
     * an item contains an array: [CakeConsumer $consumer, callable $callback];.
     *
     * @var array
     */
    private array $subscribers;

    /**
     * @var \Cake\Datasource\ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * Default 20 minutes in milliseconds.
     *
     * @var int
     */
    private int $redeliveryDelay;

    /**
     * Time to wait between subscription requests in milliseconds.
     *
     * @var int
     */
    private int $pollingInterval = 200;

    /**
     * @param \Cake\Enqueue\CakeContext $context Context instance.
     */
    public function __construct(CakeContext $context)
    {
        $this->context = $context;
        $this->connection = $this->context->getCakeConnection();
        $this->subscribers = [];

        $this->redeliveryDelay = 1200000;
    }

    /**
     * Get interval between retrying failed messages in milliseconds.
     *
     * @return int
     */
    public function getRedeliveryDelay(): int
    {
        return $this->redeliveryDelay;
    }

    /**
     * @param int $redeliveryDelay Redelivery delay.
     * @return $this
     */
    public function setRedeliveryDelay(int $redeliveryDelay)
    {
        $this->redeliveryDelay = $redeliveryDelay;

        return $this;
    }

    /**
     * @return int
     */
    public function getPollingInterval(): int
    {
        return $this->pollingInterval;
    }

    /**
     * @param int $msec Polling interval in ms.
     * @return $this
     */
    public function setPollingInterval(int $msec)
    {
        $this->pollingInterval = $msec;

        return $this;
    }

    /**
     * @param int $timeout Timeout value.
     * @return void
     */
    public function consume(int $timeout = 0): void
    {
        if (empty($this->subscribers)) {
            throw new LogicException('No subscribers');
        }

        $queueNames = [];
        foreach (array_keys($this->subscribers) as $queueName) {
            $queueNames[$queueName] = $queueName;
        }

        $timeout /= 1000;
        $now = time();
        $redeliveryDelay = $this->getRedeliveryDelay() / 1000; // milliseconds to seconds

        $currentQueueNames = [];
        while (true) {
            if (empty($currentQueueNames)) {
                $currentQueueNames = $queueNames;
            }

            $this->removeExpiredMessages();
            $this->redeliverMessages();

            $message = $this->fetchMessage($currentQueueNames, $redeliveryDelay);
            if ($message) {
                [$consumer, $callback] = $this->subscribers[$message->getQueue()];

                if (call_user_func($callback, $message, $consumer) === false) {
                    return;
                }

                unset($currentQueueNames[$message->getQueue()]);
            } else {
                $currentQueueNames = [];

                usleep($this->getPollingInterval() * 1000);
            }

            if ($timeout && microtime(true) >= $now + $timeout) {
                return;
            }
        }
    }

    /**
     * @param \Cake\Enqueue\CakeConsumer $consumer Consumer instance.
     * @param callable $callback Callback.
     * @return void
     */
    public function subscribe(Consumer $consumer, callable $callback): void
    {
        if ($consumer instanceof CakeConsumer == false) {
            $msg = sprintf('The consumer must be instance of "%s" got "%s"', CakeConsumer::class, get_class($consumer));
            throw new InvalidArgumentException($msg);
        }

        $queueName = $consumer->getQueue()->getQueueName();
        if (array_key_exists($queueName, $this->subscribers)) {
            if ($this->subscribers[$queueName][0] === $consumer && $this->subscribers[$queueName][1] === $callback) {
                return;
            }

            $msg = sprintf('There is a consumer subscribed to queue: "%s"', $queueName);
            throw new InvalidArgumentException($msg);
        }

        $this->subscribers[$queueName] = [$consumer, $callback];
    }

    /**
     * @param \Cake\Enqueue\CakeConsumer $consumer Consumer instance.
     * @return void
     */
    public function unsubscribe(Consumer $consumer): void
    {
        if ($consumer instanceof CakeConsumer == false) {
            $msg = sprintf('The consumer must be instance of "%s" got "%s"', CakeConsumer::class, get_class($consumer));
            throw new InvalidArgumentException($msg);
        }

        $queueName = $consumer->getQueue()->getQueueName();

        if (array_key_exists($queueName, $this->subscribers) == false) {
            return;
        }

        if ($this->subscribers[$queueName][0] !== $consumer) {
            return;
        }

        unset($this->subscribers[$queueName]);
    }

    /**
     * @return void
     */
    public function unsubscribeAll(): void
    {
        $this->subscribers = [];
    }

    /**
     * @return \Cake\Enqueue\CakeContext
     */
    protected function getContext(): CakeContext
    {
        return $this->context;
    }

    /**
     * @return \Cake\Database\Connection
     */
    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}
