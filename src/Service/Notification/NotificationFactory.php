<?php

namespace App\Service\Notification;

use InvalidArgumentException;

class NotificationFactory
{
    public function __construct(
        private readonly LineMessagingNotification $lineNotification,
        private readonly TelegramNotification $telegramNotification,
        private readonly NtfyNotification $ntfyNotification,
    )
    {
    }

    public function create(string $platform): NotificationServiceInterface
    {
        return match ($platform) {
            'line' => $this->lineNotification,
            'telegram' => $this->telegramNotification,
            'ntfy' => $this->ntfyNotification,
            default => throw new InvalidArgumentException('Unsupported platform: ' . $platform),
        };
    }
}
