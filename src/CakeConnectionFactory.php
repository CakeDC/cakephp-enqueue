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
    private array $config;

    /**
     * @var \Cake\Database\Connection|null
     */
    private ?Connection $connection = null;

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
     * DSN Format Examples:
     * - cakephp://default                    - Use 'default' connection with default settings
     * - cakephp://test                       - Use 'test' connection with default settings
     * - cakephp://default?table_name=queue&polling_interval=500&lazy=false
     * - cakephp://test?table_name=test_queue&polling_interval=2000
     * - cakephp://production?table_name=prod_queue&polling_interval=1000&lazy=true
     *
     * @param array|string|null $config Connection settings.
     */
    public function __construct(array|string|null $config = 'cakephp://default')
    {
        if (is_array($config)) {
            $parsedConfig = $config;
        } elseif (is_string($config)) {
            $parsedConfig = $this->parseDsn($config);
        } else {
            $parsedConfig = ['connection' => 'default'];
        }

        $this->config = array_replace_recursive(
            [
            'connection' => 'default',
            'table_name' => 'enqueue',
            'polling_interval' => 1000,
            'lazy' => true,
            ],
            $parsedConfig,
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
            $this->connection->getDriver()->disconnect();
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
    public function parseDsn(string $dsn, ?array $config = null): array
    {
        $parsedDsn = Dsn::parseFirst($dsn);

        if ($parsedDsn->getScheme() !== 'cakephp') {
            throw new Exception('Wrong dsn schema passed. Accepted only cakephp schema.');
        }

        $connectionName = $parsedDsn->getHost();

        if (empty($connectionName)) {
            $connectionName = 'default';
        }

        $query = $parsedDsn->getQuery();

        $configOverrides = [];
        foreach ($query as $key => $value) {
            switch ($key) {
                case 'polling_interval':
                    $configOverrides[$key] = (int)$value;
                    break;
                case 'lazy':
                    $configOverrides[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'table_name':
                case 'connection':
                default:
                    $configOverrides[$key] = $value;
                    break;
            }
        }

        return array_merge([
            'connection' => $connectionName,
            'lazy' => true,
        ], $configOverrides);
    }
}
