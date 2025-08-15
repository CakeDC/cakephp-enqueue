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
use Cake\Datasource\ConnectionManager;
use Enqueue\Dsn\Dsn;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Context;
use Interop\Queue\Exception\Exception;

class CakeConnectionFactory implements ConnectionFactory
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var \Cake\Database\Connection
     */
    private $connection;

    /**
     * The config could be an array, string DSN or null. In case of null it will attempt to connect to mysql localhost with default credentials.
     *
     * $config = [
     *   'connection' => []             - cakephp connection options.
     *   'table_name' => 'enqueue',     - database table name.
     *   'polling_interval' => '1000',  - How often query for new messages (milliseconds)
     *   'lazy' => true,                - Use lazy database connection (boolean)
     * ]
     *
     * or
     *
     * mysql://user:pass@localhost:3606/db?charset=UTF-8
     *
     * @param array|string|null $config Connection settings.
     */
    public function __construct($config = 'cakephp:default')
    {
        $parsedConfig = $this->parseDsn($config);

        $this->config = array_replace_recursive(
            [
            'connection' => $config ?? 'default',
            'table_name' => 'enqueue',
            'polling_interval' => 1000,
            'lazy' => true,
            ],
            $parsedConfig
        );
    }

    /**
     * @return \Cake\Enqueue\CakeContext
     */
    public function createContext(): Context
    {
        if ($this->config['lazy']) {
            return new CakeContext(function () {
                return $this->establishConnection();
            }, $this->config);
        }

        return new CakeContext($this->establishConnection(), $this->config);
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    /**
     * @return \Cake\Database\Connection
     */
    private function establishConnection(): Connection
    {
        if ($this->connection == false) {
            $this->connection = ConnectionManager::get($this->config['connection']);
            $this->connection->getDriver()->connect();
        }

        return $this->connection;
    }

    /**
     * @param string $dsn DSN string.
     * @param array|null $config Configuration.
     * @return array
     * @throws \Interop\Queue\Exception\Exception
     */
    private function parseDsn(string $dsn, ?array $config = null): array
    {
        $parsedDsn = Dsn::parseFirst($dsn);
        if ($parsedDsn->getScheme() !== 'cakephp') {
            throw new Exception('Wrong dsn schema passed. Accepted only cakephp schema.');
        }
        $dsn = str_replace('cakephp:', '', $dsn);
        $parsedDsn = Dsn::parseFirst(str_replace('cakephp', '', $dsn));
        $query = $parsedDsn->getQuery();

        return array_merge([
            'lazy' => true,
            'connection' => $parsedDsn->getPath(),
        ], $query);
    }
}
