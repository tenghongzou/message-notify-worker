<?php

namespace App\Tests\Service\Notification;

use App\Service\Notification\NtfyNotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NtfyNotificationTest extends TestCase
{
    public function testPublishesMessageBodyToTopicUrl(): void
    {
        $response = new MockResponse('');
        $client = new MockHttpClient($response);

        (new NtfyNotification($client, 'https://ntfy.sh'))
            ->setToken('')
            ->setTarget('my-topic')
            ->send('hello world');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://ntfy.sh/my-topic', $response->getRequestUrl());
        $this->assertSame('hello world', $response->getRequestOptions()['body']);
    }

    public function testTrimsTrailingSlashFromServerUrl(): void
    {
        $response = new MockResponse('');
        $client = new MockHttpClient($response);

        (new NtfyNotification($client, 'https://ntfy.example.com/'))
            ->setToken('')
            ->setTarget('my-topic')
            ->send('hi');

        $this->assertSame('https://ntfy.example.com/my-topic', $response->getRequestUrl());
    }

    public function testAddsAuthorizationHeaderWhenTokenProvided(): void
    {
        $response = new MockResponse('');
        $client = new MockHttpClient($response);

        (new NtfyNotification($client, 'https://ntfy.sh'))
            ->setToken('secret-token')
            ->setTarget('my-topic')
            ->send('hi');

        $this->assertContains('Authorization: Bearer secret-token', $response->getRequestOptions()['headers']);
    }

    public function testAttachesImageViaAttachHeader(): void
    {
        $response = new MockResponse('');
        $client = new MockHttpClient($response);

        (new NtfyNotification($client, 'https://ntfy.sh'))
            ->setToken('')
            ->setTarget('my-topic')
            ->send('hi', 'https://example.com/cat.png');

        $this->assertContains('Attach: https://example.com/cat.png', $response->getRequestOptions()['headers']);
    }

    public function testThrowsWhenTargetMissing(): void
    {
        $client = new MockHttpClient(new MockResponse(''));

        $this->expectException(RuntimeException::class);
        (new NtfyNotification($client, 'https://ntfy.sh'))->send('hello');
    }
}
