<?php

namespace App\Service\Notification;

use InvalidArgumentException;

class NotificationFactory
{
    public function __construct(
        private LineNotification $lineNotification
    )
    {
    }

    public function create(string $platform): NotificationServiceInterface
    {
        return match ($platform) {
            'line' => $this->lineNotification,
            default => throw new InvalidArgumentException('Unsupported platform: ' . $platform),
        };
    }
}
