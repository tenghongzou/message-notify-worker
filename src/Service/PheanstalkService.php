<?php declare(strict_types=1);

namespace App\Service;

use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\Job;
use Pheanstalk\Values\TubeName;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PheanstalkService
{
    const DEFAULT_TUBE_NAME = 'default';

    private Pheanstalk $pheanstalk;

    public function __construct(
        #[Autowire('%beanstalkd_host%')]
        string $beanstalkdHost
    )
    {
        $this->pheanstalk = Pheanstalk::create($beanstalkdHost);
    }

    private function ignoreDefaultTube(): void
    {
        $tube = new TubeName(self::DEFAULT_TUBE_NAME);
        $this->pheanstalk->ignore($tube);
    }

    public function reserveJob(): Job
    {
        return $this->pheanstalk->reserve();
    }

    public function reserveWithTimeout(?int $timeout): ?Job
    {
        return $this->pheanstalk->reserveWithTimeout($timeout);
    }

    public function deleteJob(Job $job): void
    {
        $this->pheanstalk->delete($job);
    }

    public function touchJob(Job $job): void
    {
        $this->pheanstalk->touch($job);
    }

    public function watch(string $tubeName): void
    {
        $tube = new TubeName($tubeName);
        $this->pheanstalk->watch($tube);
        $this->ignoreDefaultTube();
    }

    public function putJob(
        string $tubeName,
        string $data,
        int    $priority = PheanstalkPublisherInterface::DEFAULT_PRIORITY,
        int    $delay = PheanstalkPublisherInterface::DEFAULT_DELAY,
        int    $ttr = PheanstalkPublisherInterface::DEFAULT_TTR
    ): JobIdInterface
    {
        $tube = new TubeName($tubeName);
        $this->pheanstalk->useTube($tube);
        return $this->pheanstalk->put($data, $priority, $delay, $ttr);
    }

    /**
     * 查看Job的TTL（剩餘處理時間）
     *
     * @param Job $job
     * @return int|null 剩餘的處理時間（秒），如果無法獲取則返回 null
     */
    public function getJobTTL(Job $job): ?int
    {
        $stats = $this->pheanstalk->statsJob($job);
        return $stats->timeLeft ?? null;
    }
}
