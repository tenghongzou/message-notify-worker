<?php

namespace App\Tests\Service\Notification;

use App\Service\Notification\LineMessagingNotification;
use App\Service\Notification\NotificationFactory;
use App\Service\Notification\NtfyNotification;
use App\Service\Notification\TelegramNotification;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NotificationFactoryTest extends TestCase
{
    public function testCreateReturnsTheNotifierMatchingThePlatform(): void
    {
        $line = $this->createMock(LineMessagingNotification::class);
        $telegram = $this->createMock(TelegramNotification::class);
        $ntfy = $this->createMock(NtfyNotification::class);

        $factory = new NotificationFactory($line, $telegram, $ntfy);

        $this->assertSame($line, $factory->create('line'));
        $this->assertSame($telegram, $factory->create('telegram'));
        $this->assertSame($ntfy, $factory->create('ntfy'));
    }

    public function testCreateThrowsOnUnsupportedPlatform(): void
    {
        $factory = new NotificationFactory(
            $this->createMock(LineMessagingNotification::class),
            $this->createMock(TelegramNotification::class),
            $this->createMock(NtfyNotification::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $factory->create('sms');
    }
}
