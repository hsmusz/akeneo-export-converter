<?php

namespace MoveCloser\ExportConverterBundle\Step;

use Akeneo\Platform\Bundle\ImportExportBundle\Domain\Model\LocalStorage;
use Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\ItemStep;
use MoveCloser\ExportConverterBundle\Services\Converter;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class ConvertExportStep extends ItemStep
{
    protected string $datetimeFormat = 'Y-m-d_H-i-s';
    private Converter $converter;

    public function __construct(
        string $name,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        ItemReaderInterface $reader,
        ItemProcessorInterface $processor,
        ItemWriterInterface $writer,
        Converter $converter,
        int $batchSize = 100,
        JobStopper $jobStopper = null,

    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository, $reader, $processor, $writer, $batchSize, $jobStopper);

        $this->converter = $converter;
    }

    /**
     * {@inheritDoc}
     */
    public function doExecute(StepExecution $stepExecution)
    {
        parent::doExecute($stepExecution);

        $file = $this->getPath();
        $convertClass = $this->converter->matchConverter($file);

        if (!file_exists($file) || !$convertClass) {
            return;
        }

        $parameters = $this->stepExecution->getJobParameters();
        $locales = $parameters->get('filters')['structure']['locales'];

        foreach ($locales as $locale) {
            try {
                /** @var \MoveCloser\ExportConverterBundle\ExportTemplates\BaseTemplate $convertClass */
                $template = $convertClass::make($this->converter, $this->writer, $locale, $file);
                $template->convert();
                $template->saveFile($file, $locale);
                // @todo: remove excess products images
            } catch (Throwable $e) {
                dump($e->getMessage());
                throw new RuntimeException($e->getMessage());
            }
        }

        // remove original export data
        unlink($file);
    }

    private function getPath(): string
    {
        $parameters = $this->stepExecution->getJobParameters();
        $storage = $parameters->get('storage');
        $filePath = LocalStorage::TYPE === $storage['type']
            ? $storage['file_path']
            : sprintf('%s%s%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, $storage['file_path']);

        if (str_contains($filePath, '%')) {
            $datetime = $this->stepExecution->getStartTime()->format($this->datetimeFormat);
            $defaultPlaceholders = ['%datetime%' => $datetime];

            $filePath = strtr($filePath, $defaultPlaceholders);
        }

        return $filePath;
    }
}
