<?php

namespace App\Service;

use App\Service\Notification\NotificationFactory;
use Pheanstalk\Values\Job;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

class NotifyWorkerService
{
    public function __construct(
        private readonly NotificationFactory $notificationFactory
    )
    {
    }

    public function exec(Job $job)
    {
        $notification = $this->notificationFactory->create('line');
        $notification->setToken(env('LINE_NOTIFY_ACCESS_TOKEN'));
    }

}
