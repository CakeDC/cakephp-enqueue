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
namespace Cake\Enqueue\Client\Driver;

use Cake\Enqueue\CakeContext;
use Enqueue\Client\Driver\GenericDriver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @method CakeContext getContext
 */
class CakephpDriver extends GenericDriver
{
    /**
     * @param \Cake\Enqueue\CakeContext $context Driver context.
     * @param array $args Arguments.
     */
    public function __construct(CakeContext $context, ...$args)
    {
        parent::__construct($context, ...$args);
    }

    /**
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance.
     * @return void
     */
    public function setupBroker(?LoggerInterface $logger = null): void
    {
        $logger = $logger ?: new NullLogger();
        $log = function ($text, ...$args) use ($logger) {
            $logger->debug(sprintf('[CakephpDriver] ' . $text, ...$args));
        };

        $log('Creating database table: "%s"', $this->getContext()->getTableName());
        $this->getContext()->createDataBaseTable();
    }
}
