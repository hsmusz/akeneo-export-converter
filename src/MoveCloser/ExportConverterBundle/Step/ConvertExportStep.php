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
use MoveCloser\ExportConverterBundle\Services\AttributeMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConvertExportStep extends ItemStep
{
    protected string $datetimeFormat = 'Y-m-d_H-i-s';
    private AttributeMap $attributeMap;
    private array $templateMap;

    public function __construct(
        string $name,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        ItemReaderInterface $reader,
        ItemProcessorInterface $processor,
        ItemWriterInterface $writer,
        AttributeMap $attributeMap,
        int $batchSize = 100,
        JobStopper $jobStopper = null,
        array $templateMap = [],

    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository, $reader, $processor, $writer, $batchSize, $jobStopper);

        $this->attributeMap = $attributeMap;
        $this->templateMap = $templateMap;
    }

    public function doExecute(StepExecution $stepExecution)
    {
        parent::doExecute($stepExecution);

        $file = $this->getPath();
        $convertClass = $this->matchConverter($file);

        if (!file_exists($file) || !$convertClass) {
            return;
        }

        /** @var \MoveCloser\ExportConverterBundle\ExportTemplates\BaseTemplate $converter */
        $converter = new $convertClass();
        $converter->loadFile($file);
        $converter->setSelectAttributeMap($this->attributeMap->generate());
        $converter->setWriter($this->getWriter());
        $converter->convert();
        $converter->saveFile($file);
        // @todo: remove excess product images based on $converter->getImagesToSave()
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

    private function matchConverter(string $file): ?string
    {
        foreach ($this->templateMap as $tpl => $class) {
            if (!str_starts_with(basename($file), $tpl)) {
                continue;
            }

            return $class;
        }

        return null;
    }
}
