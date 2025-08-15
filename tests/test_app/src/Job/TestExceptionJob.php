<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Exception;

class TestExceptionJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        throw new Exception('Something went wrong');
    }
}
