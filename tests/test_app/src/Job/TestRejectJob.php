<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class TestRejectJob implements JobInterface
{
    public function execute(Message $message): ?string
    {
        return Processor::REJECT;
    }
}
