<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;

class TestStringJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return 'invalid value';
    }
}
