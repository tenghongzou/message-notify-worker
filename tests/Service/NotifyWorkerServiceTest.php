<?php

namespace App\Tests\Service;

use App\Service\Notification\LineMessagingNotification;
use App\Service\Notification\NotificationFactory;
use App\Service\Notification\NotificationServiceInterface;
use App\Service\Notification\NtfyNotification;
use App\Service\Notification\TelegramNotification;
use App\Service\NotifyWorkerService;
use InvalidArgumentException;
use JsonException;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\JobId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotifyWorkerServiceTest extends TestCase
{
    private const LINE_TOKEN = 'line-token';
    private const TELEGRAM_TOKEN = 'telegram-token';
    private const NTFY_TOKEN = 'ntfy-token';

    public function testDispatchesToLineWithResolvedTokenTargetAndImage(): void
    {
        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->expects($this->once())->method('setToken')
            ->with(self::LINE_TOKEN)->willReturnSelf();
        $notifier->expects($this->once())->method('setTarget')
            ->with('U123')->willReturnSelf();
        $notifier->expects($this->once())->method('send')
            ->with('hello', 'https://example.com/cat.png');

        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->once())->method('create')
            ->with('line')->willReturn($notifier);

        $this->makeService($factory)->exec($this->job([
            'platform' => 'line',
            'message' => 'hello',
            'target' => 'U123',
            'image_url' => 'https://example.com/cat.png',
        ]));
    }

    public function testPlatformDefaultsToLineWhenOmitted(): void
    {
        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->method('setToken')->willReturnSelf();
        $notifier->method('setTarget')->willReturnSelf();

        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->once())->method('create')
            ->with('line')->willReturn($notifier);

        $this->makeService($factory)->exec($this->job([
            'message' => 'hello',
            'target' => 'U123',
        ]));
    }

    public function testImageDefaultsToNullWhenOmitted(): void
    {
        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->method('setToken')->willReturnSelf();
        $notifier->method('setTarget')->willReturnSelf();
        $notifier->expects($this->once())->method('send')
            ->with('hello', null);

        $factory = $this->createMock(NotificationFactory::class);
        $factory->method('create')->willReturn($notifier);

        $this->makeService($factory)->exec($this->job([
            'message' => 'hello',
            'target' => 'U123',
        ]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function platformTokenProvider(): array
    {
        return [
            'line' => ['line', self::LINE_TOKEN],
            'telegram' => ['telegram', self::TELEGRAM_TOKEN],
            'ntfy' => ['ntfy', self::NTFY_TOKEN],
        ];
    }

    #[DataProvider('platformTokenProvider')]
    public function testResolvesTheTokenMatchingThePlatform(string $platform, string $expectedToken): void
    {
        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->expects($this->once())->method('setToken')
            ->with($expectedToken)->willReturnSelf();
        $notifier->method('setTarget')->willReturnSelf();

        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->once())->method('create')
            ->with($platform)->willReturn($notifier);

        $this->makeService($factory)->exec($this->job([
            'platform' => $platform,
            'message' => 'hello',
            'target' => 'somewhere',
        ]));
    }

    public function testTargetIsPassedThroughAndMayBeNull(): void
    {
        $notifier = $this->createMock(NotificationServiceInterface::class);
        $notifier->method('setToken')->willReturnSelf();
        $notifier->expects($this->once())->method('setTarget')
            ->with(null)->willReturnSelf();

        $factory = $this->createMock(NotificationFactory::class);
        $factory->method('create')->willReturn($notifier);

        $this->makeService($factory)->exec($this->job([
            'message' => 'hello',
        ]));
    }

    public function testThrowsWhenMessageKeyMissing(): void
    {
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(InvalidArgumentException::class);
        $this->makeService($factory)->exec($this->job([
            'platform' => 'line',
            'target' => 'U123',
        ]));
    }

    public function testThrowsWhenMessageIsEmptyString(): void
    {
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(InvalidArgumentException::class);
        $this->makeService($factory)->exec($this->job([
            'message' => '',
            'target' => 'U123',
        ]));
    }

    public function testThrowsWhenMessageIsNotAString(): void
    {
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(InvalidArgumentException::class);
        $this->makeService($factory)->exec($this->job([
            'message' => ['not', 'a', 'string'],
            'target' => 'U123',
        ]));
    }

    public function testThrowsOnInvalidJsonPayload(): void
    {
        $factory = $this->createMock(NotificationFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(JsonException::class);
        $this->makeService($factory)->exec(new Job(new JobId(1), '{ not valid json'));
    }

    public function testPropagatesUnsupportedPlatformFromRealFactory(): void
    {
        // Real factory so the unsupported-platform guard is exercised end to end.
        $factory = new NotificationFactory(
            $this->createMock(LineMessagingNotification::class),
            $this->createMock(TelegramNotification::class),
            $this->createMock(NtfyNotification::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->makeService($factory)->exec($this->job([
            'platform' => 'sms',
            'message' => 'hello',
            'target' => 'U123',
        ]));
    }

    private function makeService(NotificationFactory $factory): NotifyWorkerService
    {
        return new NotifyWorkerService(
            $factory,
            self::LINE_TOKEN,
            self::TELEGRAM_TOKEN,
            self::NTFY_TOKEN,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function job(array $payload): Job
    {
        return new Job(new JobId(1), json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
