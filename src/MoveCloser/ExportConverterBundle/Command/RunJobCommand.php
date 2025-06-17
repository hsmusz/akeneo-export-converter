<?php

namespace MoveCloser\ExportConverterBundle\Command;

use Akeneo\Tool\Bundle\BatchBundle\Job\JobInstanceRepository;
use Akeneo\Tool\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dev only Job Launcher - full sync without try/catch
 * DO NOT USE on production !!
 */
class RunJobCommand extends Command
{
    protected static $defaultName = 'app:run-job';

    public function __construct(
        private readonly JobInstanceRepository $jobInstanceRepository,
        private readonly JobLauncherInterface $jobLauncher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('jobCode', InputArgument::REQUIRED, 'Job code to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobCode = $input->getArgument('jobCode');
        /** @var \Akeneo\Tool\Component\Batch\Model\JobInstance $jobInstance */
        $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($jobCode);

        if (null === $jobInstance) {
            $output->writeln("<error>Job instance '$jobCode' not found.</error>");

            return Command::FAILURE;
        }

        $exec = $this->jobLauncher->launch($jobInstance, null, $jobInstance->getRawParameters());

        $io = new SymfonyStyle($input, $output);

        $commandInput = new ArrayInput([
            'command' => 'akeneo:batch:job',
            'code' => $jobCode,
            'execution' => $exec->getId(),
        ]);

        $commandOutput = new BufferedOutput();
        $exitCode = $this->getApplication()->run($commandInput, $commandOutput);

        $outputContent = $commandOutput->fetch();
        $io->text('Output of other command:');
        $io->writeln($outputContent);

        return $exitCode;
    }
}
