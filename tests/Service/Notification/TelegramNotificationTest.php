<?php

namespace App\Tests\Service\Notification;

use App\Service\Notification\TelegramNotification;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TelegramNotificationTest extends TestCase
{
    public function testSendsTextViaSendMessage(): void
    {
        $response = new MockResponse('{}');
        $client = new MockHttpClient($response);

        (new TelegramNotification($client))
            ->setToken('bot-token')
            ->setTarget('999')
            ->send('hello world');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://api.telegram.org/botbot-token/sendMessage', $response->getRequestUrl());

        $body = json_decode($response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['chat_id' => '999', 'text' => 'hello world'], $body);
    }

    public function testSendsImageViaSendPhotoWithCaption(): void
    {
        $response = new MockResponse('{}');
        $client = new MockHttpClient($response);

        (new TelegramNotification($client))
            ->setToken('bot-token')
            ->setTarget('999')
            ->send('caption', 'https://example.com/cat.png');

        $this->assertSame('https://api.telegram.org/botbot-token/sendPhoto', $response->getRequestUrl());

        $body = json_decode($response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'chat_id' => '999',
            'photo' => 'https://example.com/cat.png',
            'caption' => 'caption',
        ], $body);
    }

    public function testThrowsWhenTargetMissing(): void
    {
        $client = new MockHttpClient(new MockResponse('{}'));

        $this->expectException(RuntimeException::class);
        (new TelegramNotification($client))->setToken('t')->send('hello');
    }
}
