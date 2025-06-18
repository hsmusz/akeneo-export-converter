<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\Launcher;

use Akeneo\Tool\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters;
use Akeneo\Tool\Component\Batch\Job\JobRegistry;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\JobExecution;
use Akeneo\Tool\Component\Batch\Model\JobInstance;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Dev only Job Launcher - full sync without try/catch
 * DO NOT USE on production !!
 */
class LocalJobLauncher implements JobLauncherInterface
{

    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobRegistry $jobRegistry,
        private readonly string $user,
    ) {
    }

    public function launch(JobInstance $jobInstance, ?UserInterface $user, array $configuration = []): JobExecution
    {
        return $this->createJobExecution($jobInstance, $configuration);
    }

    private function createJobExecution(
        JobInstance $jobInstance,
        array $jobParameters,
    ): JobExecution {
        $job = $this->jobRegistry->get($jobInstance->getJobName());
        $jobExecution = $this->jobRepository->createJobExecution($job, $jobInstance, new JobParameters($jobParameters));
        $jobExecution->setUser($this->user);

        $this->jobRepository->updateJobExecution($jobExecution);

        return $jobExecution;
    }
}
