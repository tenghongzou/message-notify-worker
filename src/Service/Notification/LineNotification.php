<?php

namespace App\Service\Notification;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LineNotification implements NotificationServiceInterface
{
    private string $accessToken;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    )
    {
    }

    public function setToken($token): static
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * @param string $message
     * @param string|null $imageUrl
     * @return void
     * @throws TransportExceptionInterface
     */
    public function send(string $message, ?string $imageUrl = null): void
    {
        $payload = [
            'message' => $message,
        ];

        // 如果有圖片 URL，則將圖片包含到消息中
        if ($imageUrl) {
            $payload['imageThumbnail'] = $imageUrl;
            $payload['imageFullsize'] = $imageUrl;
        }

        $this->httpClient->request('POST', 'https://notify-api.line.me/api/notify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'body' => $payload,
        ]);

    }
}

