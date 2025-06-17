<?php

namespace MoveCloser\ExportConverterBundle\Enrichment;

use Akeneo\Pim\Enrichment\Component\Product\Connector\Writer\File\Xlsx\ProductWriter;
use Akeneo\Tool\Component\Connector\Writer\File\WrittenFileInfo;

/**
 * Write product data into a XLSX file on the local filesystem
 */
class XlsxProductWriter extends ProductWriter
{
    /**
     * Allow adding external files to process
     */
    public function addWrittenFile($flatFile): void
    {
        $this->writtenFiles[] = WrittenFileInfo::fromLocalFile(
            $flatFile,
            basename($flatFile)
        );
    }
}
