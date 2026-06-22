<?php

namespace App\Service\Notification;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends notifications through the Telegram Bot API.
 *
 * Token is the bot token (from @BotFather); target is the chat id to deliver to.
 *
 * @see https://core.telegram.org/bots/api
 */
class TelegramNotification implements NotificationServiceInterface
{
    private const API_BASE = 'https://api.telegram.org/bot';

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
            throw new RuntimeException('Telegram requires a target (chat id).');
        }

        // 有圖片用 sendPhoto（caption 帶文字），否則用 sendMessage
        if ($imageUrl) {
            $this->httpClient->request('POST', self::API_BASE . $this->accessToken . '/sendPhoto', [
                'json' => [
                    'chat_id' => $this->target,
                    'photo' => $imageUrl,
                    'caption' => $message,
                ],
            ]);
            return;
        }

        $this->httpClient->request('POST', self::API_BASE . $this->accessToken . '/sendMessage', [
            'json' => [
                'chat_id' => $this->target,
                'text' => $message,
            ],
        ]);
    }
}
