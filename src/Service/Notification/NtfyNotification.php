<?php

namespace App\Service\Notification;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends notifications through ntfy (https://ntfy.sh or a self-hosted server).
 *
 * Token is an optional access token (empty for public topics); target is the
 * topic name to publish to.
 *
 * @see https://docs.ntfy.sh/publish/
 */
class NtfyNotification implements NotificationServiceInterface
{
    private string $accessToken = '';
    private ?string $target = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(NTFY_SERVER_URL)%')]
        private readonly string $serverUrl,
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
            throw new RuntimeException('ntfy requires a target (topic).');
        }

        $headers = [];
        // 可選的存取權杖（公開 topic 可留空）
        if ($this->accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }
        // ntfy 以 Attach 標頭夾帶圖片 URL
        if ($imageUrl) {
            $headers['Attach'] = $imageUrl;
        }

        $this->httpClient->request('POST', rtrim($this->serverUrl, '/') . '/' . $this->target, [
            'headers' => $headers,
            'body' => $message,
        ]);
    }
}
