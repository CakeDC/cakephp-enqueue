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

use Interop\Queue\Queue;
use Interop\Queue\Topic;

class CakeDestination implements Topic, Queue
{
    /**
     * @var string
     */
    private string $destinationName;

    /**
     * @param string $name Destination name.
     */
    public function __construct(string $name)
    {
        $this->destinationName = $name;
    }

    /**
     * @return string
     */
    public function getQueueName(): string
    {
        return $this->destinationName;
    }

    /**
     * @return string
     */
    public function getTopicName(): string
    {
        return $this->destinationName;
    }
}
