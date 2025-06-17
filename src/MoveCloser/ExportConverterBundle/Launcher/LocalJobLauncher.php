<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\Launcher;

use Akeneo\Platform\Bundle\ImportExportBundle\Repository\InternalApi\JobExecutionRepository;
use Akeneo\Tool\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Akeneo\Tool\Bundle\BatchQueueBundle\Manager\JobExecutionManager;
use Akeneo\Tool\Component\Batch\Job\JobParameters;
use Akeneo\Tool\Component\Batch\Job\JobRegistry;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\JobExecution;
use Akeneo\Tool\Component\Batch\Model\JobInstance;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Dev only Job Launcher - full sync without try/catch
 * DO NOT USE on production !!
 */
class LocalJobLauncher implements JobLauncherInterface
{
    /**
     * Interval in seconds before checking if the process is still running.
     */
    private const RUNNING_PROCESS_CHECK_INTERVAL = 5;

    public function __construct(
        private JobExecutionManager $executionManager,
        private JobRepositoryInterface $jobRepository,
        private JobExecutionRepository $jobExecutionRepository,
        private LoggerInterface $logger,
        private JobRegistry $jobRegistry,
        private string $projectDir,
        private string $user
    ) {
    }

    public function launch(JobInstance $jobInstance, ?UserInterface $user, array $configuration = []): JobExecution
    {
        return $this->createJobExecution($jobInstance, $user, $configuration);
    }

    private function createJobExecution(JobInstance $jobInstance, ?UserInterface $user, array $jobParameters): JobExecution
    {
        $job = $this->jobRegistry->get($jobInstance->getJobName());
        $jobExecution = $this->jobRepository->createJobExecution($job, $jobInstance, new JobParameters($jobParameters));
        $jobExecution->setUser($this->user);

        $this->jobRepository->updateJobExecution($jobExecution);

        return $jobExecution;
    }
}
