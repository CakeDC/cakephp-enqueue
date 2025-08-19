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
namespace Cake\Enqueue\Model\Entity;

use Cake\ORM\Entity;

/**
 * Enqueue Entity
 *
 * @property string $id
 * @property int|null $published_at
 * @property string|null $body
 * @property string|null $headers
 * @property string|null $properties
 * @property bool|null $redelivered
 * @property string|null $queue
 * @property int|null $priority
 * @property int|null $delayed_until
 * @property int|null $time_to_live
 * @property string|null $delivery_id
 * @property int|null $redeliver_after
 *
 * @property \App\Model\Entity\Delivery $delivery
 */
class Enqueue extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'published_at' => true,
        'body' => true,
        'headers' => true,
        'properties' => true,
        'redelivered' => true,
        'queue' => true,
        'priority' => true,
        'delayed_until' => true,
        'time_to_live' => true,
        'delivery_id' => true,
        'redeliver_after' => true,
        'delivery' => true,
    ];
}
