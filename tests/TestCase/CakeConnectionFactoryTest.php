<?php
declare(strict_types=1);

namespace Cake\Enqueue\Test\TestCase;

use Cake\Enqueue\CakeConnectionFactory;
use Cake\TestSuite\TestCase;
use Interop\Queue\Exception\Exception;
use ReflectionClass;

/**
 * CakeConnectionFactory Test Case
 */
class CakeConnectionFactoryTest extends TestCase
{
    /**
     * Test parseDsn with standard DSN format
     *
     * @return void
     */
    public function testParseDsnStandardFormat(): void
    {
        $factory = new CakeConnectionFactory(['connection' => 'test']);

        $result = $factory->parseDsn('cakephp://test');

        $this->assertEquals('test', $result['connection']);
        $this->assertEquals('enqueue', $result['table_name'] ?? 'enqueue');
        $this->assertTrue($result['lazy']);
    }

    /**
     * Test parseDsn with query parameters
     *
     * @return void
     */
    public function testParseDsnWithQueryParameters(): void
    {
        $factory = new CakeConnectionFactory(['connection' => 'test']);

        $result = $factory->parseDsn('cakephp://production?table_name=prod_queue&polling_interval=2000&lazy=false');

        $this->assertEquals('production', $result['connection']);
        $this->assertEquals('prod_queue', $result['table_name']);
        $this->assertEquals(2000, $result['polling_interval']);
        $this->assertFalse($result['lazy']);
    }

    /**
     * Test parseDsn with empty connection defaults to 'default'
     *
     * @return void
     */
    public function testParseDsnEmptyConnectionDefaultsToDefault(): void
    {
        $factory = new CakeConnectionFactory(['connection' => 'test']);

        $result = $factory->parseDsn('cakephp://');

        $this->assertEquals('default', $result['connection']);
        $this->assertTrue($result['lazy']);
    }

    /**
     * Test parseDsn with invalid scheme throws exception
     *
     * @return void
     */
    public function testParseDsnInvalidSchemeThrowsException(): void
    {
        $factory = new CakeConnectionFactory(['connection' => 'test']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wrong dsn schema passed. Accepted only cakephp schema.');

        $factory->parseDsn('mysql://localhost');
    }

    /**
     * Test constructor with array config
     *
     * @return void
     */
    public function testConstructorWithArrayConfig(): void
    {
        $config = [
            'connection' => 'custom',
            'table_name' => 'custom_table',
            'polling_interval' => 3000,
            'lazy' => false,
        ];

        $factory = new CakeConnectionFactory($config);

        $reflection = new ReflectionClass($factory);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $actualConfig = $configProperty->getValue($factory);

        $this->assertEquals('custom', $actualConfig['connection']);
        $this->assertEquals('custom_table', $actualConfig['table_name']);
        $this->assertEquals(3000, $actualConfig['polling_interval']);
        $this->assertFalse($actualConfig['lazy']);
    }
}
