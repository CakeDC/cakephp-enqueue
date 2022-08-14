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
use Ramsey\Uuid\Uuid;

trait CakeConsumerHelperTrait
{
    private $redeliverMessagesLastExecutedAt;

    private $removeExpiredMessagesLastExecutedAt;

    /**
     * @return \Cake\Enqueue\CakeContext
     */
    abstract protected function getContext(): CakeContext;

    /**
     * @return \Cake\Database\Connection
     */
    abstract protected function getConnection(): Connection;

    /**
     * @param array $queues Queues.
     * @param int $redeliveryDelay Redelivery delay/
     * @return \Cake\Enqueue\CakeMessage|null
     */
    protected function fetchMessage(array $queues, int $redeliveryDelay): ?CakeMessage
    {
        if (empty($queues)) {
            throw new \LogicException('Queues must not be empty.');
        }

        $now = time();
        $deliveryId = Uuid::uuid4();

        $endAt = microtime(true) + 0.2;

        $select = $this->getContext()->getTable()->find()
            ->select(['id'])
            ->where([
                'queue IN' => $queues,
                'OR' => [
                    'delayed_until IS' => null,
                    'delayed_until <=' => $now,
                ],
                'delivery_id IS' => null,
            ])
            ->order(['priority', 'published_at']);

        while (microtime(true) < $endAt) {
            try {
                $result = $select->first();
                if (empty($result)) {
                    return null;
                }

                $updateResult = $this->getContext()->getTable()->updateAll(
                    [
                        'delivery_id' => $deliveryId,
                        'redeliver_after' => $now + $redeliveryDelay,
                    ],
                    [
                        'id' => $result['id'],
                        'delivery_id IS' => null,
                    ]
                );

                if ($updateResult) {
                    $deliveredMessage = $this->getContext()->getTable()->find()
                        ->where(['delivery_id' => $deliveryId])
                        ->first();

                    // the message has been removed by a 3rd party, such as truncate operation.
                    if ($deliveredMessage === null) {
                        continue;
                    }

                    if (
                        $deliveredMessage['redelivered'] ||
                        empty($deliveredMessage['time_to_live']) ||
                        $deliveredMessage['time_to_live'] > time()
                    ) {
                        return $this->getContext()->convertMessage($deliveredMessage->toArray());
                    }
                }
            } catch (\Exception $e) {
                // maybe next time we'll get more luck
            }
        }

        return null;
    }

    /**
     * @return void
     */
    protected function redeliverMessages(): void
    {
        if ($this->redeliverMessagesLastExecutedAt === null) {
            $this->redeliverMessagesLastExecutedAt = microtime(true);
        } elseif (microtime(true) - $this->redeliverMessagesLastExecutedAt < 1) {
            return;
        }
        try {
            $updateResult = $this->getContext()->getTable()->updateAll(
                [
                    'delivery_id' => null,
                    'redeliver' => true,
                ],
                [
                    'redeliver_after <' => time(),
                    'delivery_id IS NOT' => null,
                ]
            );

            $this->redeliverMessagesLastExecutedAt = microtime(true);
        } catch (\Exception $e) {
            // maybe next time we'll get more luck
        }
    }

    /**
     * @return void
     */
    protected function removeExpiredMessages(): void
    {
        if ($this->removeExpiredMessagesLastExecutedAt === null) {
            $this->removeExpiredMessagesLastExecutedAt = microtime(true);
        } elseif (microtime(true) - $this->removeExpiredMessagesLastExecutedAt < 1) {
            return;
        }

        try {
            $this->getContext()->getTable()->deleteAll([
                'time_to_live IS NOT' => null,
                'time_to_live <=' => time(),
                'redelivered' => false,
                'delivery_id IS' => null,
            ]);
        } catch (\Exception $e) {
            // maybe next time we'll get more luck
        }

        $this->removeExpiredMessagesLastExecutedAt = microtime(true);
    }

    /**
     * @param string $deliveryId Delivery id.
     * @return void
     */
    private function deleteMessage(string $deliveryId): void
    {
        if (empty($deliveryId)) {
            $msg = sprintf('Expected record was removed but it is not. Delivery id: "%s"', $deliveryId);
            throw new \LogicException($msg);
        }

        $this->getContext()->getTable()->deleteAll(
            ['delivery_id' => $deliveryId]
        );
    }
}
