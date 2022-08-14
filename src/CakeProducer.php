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

use Interop\Queue\Destination;
use Interop\Queue\Exception\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Ramsey\Uuid\Uuid;

class CakeProducer implements Producer
{
    /**
     * @var int|null
     */
    private $priority;

    /**
     * @var int|float|null
     */
    private $deliveryDelay;

    /**
     * @var int|float|null
     */
    private $timeToLive;

    /**
     * @var \Cake\Enqueue\CakeContext
     */
    private $context;

    /**
     * @param \Cake\Enqueue\CakeContext $context Context instance.
     */
    public function __construct(CakeContext $context)
    {
        $this->context = $context;
    }

    /**
     * @param \Cake\Enqueue\CakeDestination $destination Message destination
     * @param \Cake\Enqueue\CakeMessage $message Message instance.
     * @return void
     */
    public function send(Destination $destination, Message $message): void
    {
        InvalidDestinationException::assertDestinationInstanceOf($destination, CakeDestination::class);
        InvalidMessageException::assertMessageInstanceOf($message, CakeMessage::class);

        if ($this->priority !== null && $message->getPriority() === null) {
            $message->setPriority($this->priority);
        }
        if ($this->deliveryDelay !== null && $message->getDeliveryDelay() === null) {
            $message->setDeliveryDelay($this->deliveryDelay);
        }
        if ($this->timeToLive !== null && $message->getTimeToLive() === null) {
            $message->setTimeToLive($this->timeToLive);
        }

        $body = $message->getBody();

        $publishedAt = $message->getPublishedAt() ??
            (int)(microtime(true) * 10000);

        $record = [
            'id' => (string)Uuid::uuid4(),
            'published_at' => $publishedAt,
            'body' => $body,
            'headers' => JSON::encode($message->getHeaders()),
            'properties' => JSON::encode($message->getProperties()),
            'priority' => -1 * $message->getPriority(),
            'queue' => $destination->getQueueName(),
            'redelivered' => false,
            'delivery_id' => null,
            'redeliver_after' => null,
        ];

        $delay = $message->getDeliveryDelay();
        if ($delay) {
            if (!is_int($delay)) {
                $result = is_object($delay) ? get_class($delay) : gettype($delay);
                throw new \LogicException(sprintf('Delay must be integer but got: "%s"', $result));
            }

            if ($delay <= 0) {
                throw new \LogicException(sprintf('Delay must be positive integer but got: "%s"', $delay));
            }

            $record['delayed_until'] = time() + (int)($delay / 1000);
        }

        $timeToLive = $message->getTimeToLive();
        if ($timeToLive) {
            if (!is_int($timeToLive)) {
                $value = is_object($timeToLive) ? get_class($timeToLive) : gettype($timeToLive);
                throw new \LogicException(sprintf('TimeToLive must be integer but got: "%s"', $value));
            }

            if ($timeToLive <= 0) {
                throw new \LogicException(sprintf('TimeToLive must be positive integer but got: "%s"', $timeToLive));
            }

            $record['time_to_live'] = time() + (int)($timeToLive / 1000);
        }

        try {
            $rowsAffected = $this->context->getCakeConnection()->insert($this->context->getTableName(), $record);

            if ($rowsAffected->rowCount() !== 1) {
                throw new Exception('The message was not enqueued. Cake did not confirm that the record is inserted.');
            }
        } catch (\Exception $e) {
            throw new Exception('The transport fails to send the message due to some internal error.', 0, $e);
        }
    }

    /**
     * @param int|null $deliveryDelay Delivery delay.
     * @return \Interop\Queue\Producer
     */
    public function setDeliveryDelay(?int $deliveryDelay = null): Producer
    {
        $this->deliveryDelay = $deliveryDelay;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getDeliveryDelay(): ?int
    {
        return $this->deliveryDelay;
    }

    /**
     * @param int|null $priority Priority.
     * @return \Interop\Queue\Producer
     */
    public function setPriority(?int $priority = null): Producer
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPriority(): ?int
    {
        return $this->priority;
    }

    /**
     * @param int|null $timeToLive Time to live.
     * @return \Interop\Queue\Producer
     */
    public function setTimeToLive(?int $timeToLive = null): Producer
    {
        $this->timeToLive = $timeToLive;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTimeToLive(): ?int
    {
        return $this->timeToLive;
    }
}
