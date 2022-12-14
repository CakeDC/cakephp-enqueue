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
namespace Cake\Queue\Test\TestCase\Mailer;

use Cake\Mailer\Exception\MissingActionException;
use Cake\ORM\TableRegistry;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;
use TestApp\WelcomeMailer;

class QueueTraitTest extends TestCase
{
    /**
     * Test that a MissingActionException is being thrown when
     * the push action is not found on the object with the QueueTrait
     *
     * @return @void
     */
    public function testQueueTraitTestThrowsMissingActionException()
    {
        $queue = new WelcomeMailer();
        $this->expectException(MissingActionException::class);
        $queue->push('nonExistentFunction');
    }

    /**
     * Test that QueueTrait calls push
     *
     * @runInSeparateProcess
     * @return @void
     */
    public function testQueueTraitCallsPush()
    {
        $application = new \TestApp\Application(CONFIG);
        $plugin = new \Cake\Enqueue\Plugin();
        $plugin->bootstrap($application);

        $queue = new WelcomeMailer();
        QueueManager::setConfig('default', [
            'queue' => 'default',
            'url' => 'cakephp:connection:test',
        ]);

        $this->assertEmpty($queue->push('welcome'));

        $testConection = \Cake\Datasource\ConnectionManager::get('test');
        $Enqueue = TableRegistry::getTableLocator()->get('Cake/Enqueue.Enqueue', [
            'connection' => $testConection,
        ]);
        $entites = $Enqueue->find()->all()->toArray();
        $this->assertCount(1, $entites);
        $this->assertEquals('enqueue.app.default', $entites[0]->queue);
        $this->assertEquals('{"class":["Cake\\\\Queue\\\\Job\\\\MailerJob","execute"],"args":[{"mailerConfig":null,"mailerName":"TestApp\\\\WelcomeMailer","action":"welcome","args":[],"headers":[]}],"data":{"mailerConfig":null,"mailerName":"TestApp\\\\WelcomeMailer","action":"welcome","args":[],"headers":[]}}', $entites[0]->body);
    }
}
