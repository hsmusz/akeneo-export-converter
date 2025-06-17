<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\ExportTemplates;

use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseTemplate
{
    protected array $availableLangs = [
        'pl_PL',
        'en_GB',
        'es_MX',
        'de_DE',
        'fr_FR',
    ];
    protected string $detectedLang;
    protected bool $imagesFromParent = false;
    protected array $imagesToSave = [];
    protected array $productGroups = [];
    protected array $selectAttributeMap;
    protected int|string|null $sheetName = null;
    protected array $sourceColumnMap;
    protected Spreadsheet $spreadsheet;
    protected Spreadsheet $spreadsheetSource;
    protected int $startingRow;
    protected string $templateFile;
    protected ?Worksheet $worksheet;
    protected ?ItemWriterInterface $writer;

    public function convert(): void
    {
        $this->resolveWorksheet();
        $rows = $this->prepareRows();

        $i = $this->startingRow;
        foreach ($rows as $sourceRow) {
            $this->detectImagesToSave($this->sourceColumnMap, $sourceRow);
            $this->detectProductGroups($this->sourceColumnMap, $sourceRow);

            foreach ($this->sheetsColumnMap() as $destinationCol => $sourceColumnName) {
                $this->writeRow(
                    [$destinationCol, $i],
                    $sourceColumnName,
                    $this->columnConvert(
                        $destinationCol,
                        $sourceColumnName,
                        $sourceRow
                    )
                );
            }

            $i++;
        }

        $this->correctProductGroupsImages();
    }

    public function getImagesToSave(): array
    {
        return $this->imagesToSave;
    }

    public function loadFile($file): void
    {
        $this->spreadsheet = IOFactory::load($this->templateFile);
        $this->spreadsheetSource = IOFactory::load($file);
    }

    public function saveFile($file): void
    {
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save(dirname($file) . '/' . basename($file));
    }

    public function setSelectAttributeMap($selectAttributeMap): void
    {
        $this->selectAttributeMap = $selectAttributeMap;
    }

    public function setWriter(?ItemWriterInterface $writer): void
    {
        $this->writer = $writer;
    }

    protected function changeWeight($value, $col, $row, $num)
    {
        if (empty($value) || empty($num)) {
            return $value;
        }

        return $value * $num;
    }

    protected function cleanText($value): string
    {
        $pattern = '/&nbsp;/';
        $replacement = '';
        $result = preg_replace($pattern, $replacement, $value ?? '');

        return strip_tags($result ?? '');
    }

    protected function columnConvert(int $col, string $sourceColumnName, array $row): mixed
    {
        if (!array_key_exists($sourceColumnName, $this->sourceColumnMap)) {
            return null;
        }

        $val = $row[$this->sourceColumnMap[$sourceColumnName]];
        $map = $this->columnConvertMap();

        return isset($map[$col]) ? $map[$col]($val, $col, $row) : $val;
    }

    abstract protected function columnConvertMap(): array;

    protected function detectLang(array $sourceColumnMap): void
    {
        foreach ($sourceColumnMap as $column => $index) {
            foreach ($this->availableLangs as $lang) {
                if (str_contains($column, $lang)) {
                    $this->detectedLang = $lang;

                    return;
                }
            }
        }
    }

    protected function detectProductGroups(array $columnMap, $row): void
    {
        if (isset($columnMap['parent']) && !empty($row[$columnMap['parent']])) {
            $this->productGroups[$row[$columnMap['parent']]][] = $row[$columnMap['sku']];
        }
    }

    abstract protected function getDataType(string $col): ?string;

    protected function isHeaderRow(array $sourceRow): bool
    {
        return $sourceRow[0] === 'sku' || $sourceRow[1] === 'sku';
    }

    protected function prepareRows(): array
    {
        $rows = $this->spreadsheetSource->getActiveSheet()->toArray();
        $headerRows = array_shift($rows);
        $this->sourceColumnMap = array_flip($headerRows);
        $this->sourceColumnMap[''] = null;
        $this->detectLang($this->sourceColumnMap);

        return $rows;
    }

    protected function resolveWorksheet(): void
    {
        if (is_int($this->sheetName)) {
            $this->worksheet = $this->spreadsheet->getSheet($this->sheetName);
        } elseif (is_string($this->sheetName)) {
            $this->worksheet = $this->spreadsheet->getSheetByName($this->sheetName);
        } else {
            $this->worksheet = $this->spreadsheet->getActiveSheet();
        }
    }

    protected function selectAttribute($value, $col, $row)
    {
        if (empty($value)) {
            return $value;
        }

        if (str_contains($value, ',')) {
            $attributes = explode(',', $value);
            $attributesLabeled = [];
            foreach ($attributes as $attribute) {
                $attributesLabeled[] = $this->selectAttribute($attribute, $col, $row);
            }

            return implode(',', $attributesLabeled);
        }

        return $this->getMappedAttribute($col, $value);
    }

    abstract protected function sheetsColumnMap(): array;

    protected function writeRow(array $coords, string $sourceColumnName, mixed $value): void
    {
        $this->worksheet
            ->getCell($coords)
            ->setValue($value);

        if (!is_null($this->getDataType($sourceColumnName))) {
            $this->worksheet
                ->getCell($coords)
                ->setDataType(
                    $this->getDataType($sourceColumnName)
                );
        }
    }

    private function getMappedAttribute(int $col, mixed $value): mixed
    {
        $colMap = $this->sheetsColumnMap();

        if (!isset($this->selectAttributeMap[$colMap[$col]])) {
            echo sprintf(
                    "- Err0r: cant find labels definitions for field [%s].\n",
                    $colMap[$col]
                ) . PHP_EOL;

            return $value;
        }

        if (!isset($this->selectAttributeMap[$colMap[$col]][$value])) {
            echo sprintf(
                    "- Err0r: cant find label for field [%s] and value [%s].\n",
                    $colMap[$col],
                    $value
                ) . PHP_EOL;

            return $value;
        }

        if (!isset($this->selectAttributeMap[$colMap[$col]][$value][$this->detectedLang])) {
            echo sprintf(
                    "- Err0r: cant find label translation for field [%s], value [%s] and lang [%s].\n",
                    $colMap[$col],
                    $value,
                    $this->detectedLang
                ) . PHP_EOL;

            return $value;
        }

        return $this->selectAttributeMap[$colMap[$col]][$value][$this->detectedLang];
    }
}
