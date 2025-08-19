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
use Interop\Queue\Consumer;
use Interop\Queue\Exception\InvalidMessageException;
use Interop\Queue\Impl\ConsumerPollingTrait;
use Interop\Queue\Message;
use Interop\Queue\Queue;

class CakeConsumer implements Consumer
{
    use ConsumerPollingTrait;
    use CakeConsumerHelperTrait;

    /**
     * @var \Cake\Enqueue\CakeContext
     */
    private CakeContext $context;

    /**
     * @var \Cake\Database\Connection
     */
    private Connection $connection;

    /**
     * @var \Cake\Enqueue\CakeDestination
     */
    private CakeDestination $queue;

    /**
     * Default 20 minutes in milliseconds.
     *
     * @var int
     */
    private int $redeliveryDelay;

    /**
     * @param \Cake\Enqueue\CakeContext $context Context instance.
     * @param \Cake\Enqueue\CakeDestination $queue Destination instance.
     */
    public function __construct(CakeContext $context, CakeDestination $queue)
    {
        $this->context = $context;
        $this->queue = $queue;
        $this->connection = $this->context->getCakeConnection();

        $this->redeliveryDelay = 1200000;
    }

    /**
     * Get interval between retry failed messages in milliseconds.
     *
     * @return int
     */
    public function getRedeliveryDelay(): int
    {
        return $this->redeliveryDelay;
    }

    /**
     * Get interval between retrying failed messages in milliseconds.
     *
     * @param int $redeliveryDelay Delay in milliseconds.
     * @return self
     */
    public function setRedeliveryDelay(int $redeliveryDelay): self
    {
        $this->redeliveryDelay = $redeliveryDelay;

        return $this;
    }

    /**
     * @return \Cake\Enqueue\CakeDestination
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * @return \Interop\Queue\Message|null
     */
    public function receiveNoWait(): ?Message
    {
        $redeliveryDelay = $this->getRedeliveryDelay() / 1000;

        $this->removeExpiredMessages();
        $this->redeliverMessages();

        return $this->fetchMessage([$this->queue->getQueueName()], $redeliveryDelay);
    }

    /**
     * @param \Cake\Enqueue\CakeMessage $message Message instance.
     * @return void
     */
    public function acknowledge(Message $message): void
    {
        InvalidMessageException::assertMessageInstanceOf($message, CakeMessage::class);

        $this->deleteMessage($message->getDeliveryId());
    }

    /**
     * @param \Cake\Enqueue\CakeMessage $message Message instance.
     * @param bool $requeue Requeue flag.
     * @return void
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     */
    public function reject(Message $message, bool $requeue = false): void
    {
        InvalidMessageException::assertMessageInstanceOf($message, CakeMessage::class);

        if ($requeue) {
            $message = clone $message;
            $message->setRedelivered(false);

            $this->getContext()->createProducer()->send($this->queue, $message);
        }

        $this->acknowledge($message);
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
