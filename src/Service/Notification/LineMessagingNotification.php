<?php

namespace App\Service\Notification;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends push messages through the LINE Messaging API — the official successor
 * to the retired LINE Notify (terminated 2025-03-31).
 *
 * Token is the channel access token; target is the recipient id
 * (user / group / room id).
 *
 * @see https://developers.line.biz/en/docs/messaging-api/sending-messages/
 */
class LineMessagingNotification implements NotificationServiceInterface
{
    private const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    private string $accessToken = '';
    private ?string $target = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    )
    {
    }

    public function setToken(string $token): static
    {
        $this->accessToken = $token;
        return $this;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function send(string $message, ?string $imageUrl = null): void
    {
        if (!$this->target) {
            throw new RuntimeException('LINE Messaging API requires a target (user/group/room id).');
        }

        $messages = [
            ['type' => 'text', 'text' => $message],
        ];

        // LINE 圖片必須是 HTTPS URL，且需同時提供原圖與預覽圖
        if ($imageUrl) {
            $messages[] = [
                'type' => 'image',
                'originalContentUrl' => $imageUrl,
                'previewImageUrl' => $imageUrl,
            ];
        }

        $this->httpClient->request('POST', self::PUSH_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'json' => [
                'to' => $this->target,
                'messages' => $messages,
            ],
        ]);
    }
}
