<?php

namespace App\Tests\Service\Notification;

use App\Service\Notification\LineMessagingNotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LineMessagingNotificationTest extends TestCase
{
    public function testSendsTextPushToTheMessagingApiEndpoint(): void
    {
        $response = new MockResponse('{}');
        $client = new MockHttpClient($response);

        (new LineMessagingNotification($client))
            ->setToken('channel-token')
            ->setTarget('U123')
            ->send('hello world');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.line.me/v2/bot/message/push', $response->getRequestUrl());

        $options = $response->getRequestOptions();
        $this->assertHeaderContains($options, 'Authorization: Bearer channel-token');

        $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('U123', $body['to']);
        $this->assertSame(
            [['type' => 'text', 'text' => 'hello world']],
            $body['messages'],
        );
    }

    public function testAppendsAnImageMessageWhenImageUrlGiven(): void
    {
        $response = new MockResponse('{}');
        $client = new MockHttpClient($response);

        (new LineMessagingNotification($client))
            ->setToken('channel-token')
            ->setTarget('U123')
            ->send('caption', 'https://example.com/cat.png');

        $body = json_decode($response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            [
                ['type' => 'text', 'text' => 'caption'],
                [
                    'type' => 'image',
                    'originalContentUrl' => 'https://example.com/cat.png',
                    'previewImageUrl' => 'https://example.com/cat.png',
                ],
            ],
            $body['messages'],
        );
    }

    public function testThrowsWhenTargetMissing(): void
    {
        $client = new MockHttpClient(new MockResponse('{}'));

        $this->expectException(RuntimeException::class);
        (new LineMessagingNotification($client))->setToken('t')->send('hello');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertHeaderContains(array $options, string $expected): void
    {
        $this->assertContains($expected, $options['headers']);
    }
}
