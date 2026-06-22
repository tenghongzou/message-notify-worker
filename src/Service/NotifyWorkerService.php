<?php

namespace App\Service;

use App\Service\Notification\NotificationFactory;
use InvalidArgumentException;
use Pheanstalk\Values\Job;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NotifyWorkerService
{
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        #[Autowire('%env(LINE_MESSAGING_CHANNEL_ACCESS_TOKEN)%')]
        private readonly string $lineToken,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $telegramToken,
        #[Autowire('%env(NTFY_AUTH_TOKEN)%')]
        private readonly string $ntfyToken,
    )
    {
    }

    /**
     * Dispatch a single queued job to its target notification platform.
     *
     * Expected JSON payload:
     *   {
     *     "platform":  "line" | "telegram" | "ntfy",      // optional, defaults to "line"
     *     "message":   "text to send",                    // required
     *     "target":    "<user/group id | chat id | topic>", // required (recipient)
     *     "image_url": "https://..."                      // optional
     *   }
     */
    public function exec(Job $job): void
    {
        $payload = json_decode($job->getData(), true, 512, JSON_THROW_ON_ERROR);

        $platform = $payload['platform'] ?? 'line';

        $message = $payload['message'] ?? null;
        if (!is_string($message) || $message === '') {
            throw new InvalidArgumentException('Job payload is missing a non-empty "message".');
        }

        $target = $payload['target'] ?? null;
        $imageUrl = $payload['image_url'] ?? null;

        $this->notificationFactory->create($platform)
            ->setToken($this->resolveToken($platform))
            ->setTarget($target)
            ->send($message, $imageUrl);
    }

    private function resolveToken(string $platform): string
    {
        return match ($platform) {
            'line' => $this->lineToken,
            'telegram' => $this->telegramToken,
            'ntfy' => $this->ntfyToken,
            default => throw new InvalidArgumentException('No token configured for platform: ' . $platform),
        };
    }
}
