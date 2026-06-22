<?php

namespace App\Command;

use App\Service\PheanstalkService;
use App\Service\NotifyWorkerService;
use LeoCarmo\GracefulShutdown\GracefulShutdown;
use Pheanstalk\Contract\JobIdInterface;
use Pheanstalk\Exception\ConnectionException;
use Pheanstalk\Values\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'app:notify-worker',
    description: 'Notify Worker Command',
    aliases: ['app:notify'],
)]
class NotifyWorkerCommand extends Command
{
    const TUBE_LOW_PRIORITY = 2048;
    const TUBE_RETRY_DELAY = 10;
    const JOB_RETRY_DELAY = 20;
    const MAX_RETRIES = 3;
    const TUBE_NAME = 'notify-message';
    const FAIL_TUBE_NAME = "notify-message.fail.job";

    public function __construct(
        private readonly LoggerInterface     $logger,
        private readonly PheanstalkService   $pheanstalkService,
        private readonly NotifyWorkerService $notifyWorkerService,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Listening to Notify Worker for jobs...');

        $gracefulShutdown = new GracefulShutdown();
        while (!$gracefulShutdown->signalReceived()) {
            try {
                $this->runWorker();
            } catch (ConnectionException $e) {
                $this->logger->warning('Restart Listening to Notify Worker for jobs...');
                $this->execute($input, $output);
            } catch (Throwable $e) {
                $this->logger->error("[" . static::class . "] " . $e->getMessage());
            }
        }

        $this->logger->info('Graceful shutdown!');
        return Command::SUCCESS;
    }

    private function runWorker(): void
    {
        $this->pheanstalkService->watch(static::TUBE_NAME);
        // this hangs until a Job is produced.
        $job = $this->pheanstalkService->reserveWithTimeout(0);

        if ($job) {
            try {
                $jobPayload = $job->getData();
                $jobData = json_decode($jobPayload, true);
                $this->logger->info(static::TUBE_NAME . " Put Job info" . ':' . $jobPayload);
                $retryCount = $jobData['retry_count'] ?? 0;
                if ($retryCount >= self::MAX_RETRIES) {
                    $this->handleJobMaxRetriesToFail($job);
                    return;
                }

                $this->notifyWorkerService->exec($job);

                $this->pheanstalkService->deleteJob($job);
            } catch (Throwable $e) {
                $this->logger->error("[" . static::class . "] " . $e);
                $this->handleJobRetry($job, $e);
            }
        }
    }

    protected function handleJobRetry(Job $job, Throwable $e): JobIdInterface
    {
        $jobData = json_decode($job->getData(), true);
        $failMessage = $e->getMessage();
        $jobData['retry_count'] = isset($jobData['retry_count']) ? $jobData['retry_count'] + 1 : 1;
        $jobData['fail_message'] = $failMessage;
        $this->logger->error(static::class . $e);
        $this->pheanstalkService->deleteJob($job);
        return $this->pheanstalkService->putJob(
            static::TUBE_NAME,
            json_encode($jobData, JSON_UNESCAPED_UNICODE),
            self::TUBE_LOW_PRIORITY,
            self::JOB_RETRY_DELAY
        );
    }

    /**
     * @param Job $job
     * @return JobIdInterface
     */
    protected function handleJobMaxRetriesToFail(Job $job): JobIdInterface
    {
        $jobData = json_decode($job->getData(), true);
        $this->logger->error(static::class . "Max retry count reached. Marking job as failed.");
        $failJob = $this->pheanstalkService->putJob(static::FAIL_TUBE_NAME, json_encode($jobData, JSON_UNESCAPED_UNICODE));
        // eventually we're done, delete job.
        $this->pheanstalkService->deleteJob($job);
        return $failJob;
    }
}
