<?php

declare(strict_types=1);

namespace MoveCloser\ExportConverterBundle\ExportTemplates;

use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use MoveCloser\ExportConverterBundle\Services\Converter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseTemplate
{
    protected Converter $converter;
    protected int $counter = 0;
    protected array $dataTypesMap = [];
    protected ?string $detectedCurrency = null;
    protected ?string $detectedLang = null;
    protected int|string|null $sheetName = null;
    protected array $sourceColumnMap;
    protected Spreadsheet $spreadsheet;
    protected Spreadsheet $spreadsheetSource;
    protected int $startingRow;
    protected string $templateFile;
    protected ?Worksheet $worksheet;
    protected ?ItemWriterInterface $writer;

    /**
     * Creates a new template instance and prepares it for conversion.
     *
     * This static factory method initializes the template with a converter and writer, and loads the specified file.
     * It is the recommended way to instantiate and prepare a template for further processing.
     */
    public static function make(Converter $converter, ItemWriterInterface $writer, string $locale, string $file): static
    {
        $template = new static();
        $template->converter = $converter;
        $template->writer = $writer;
        $template->detectedLang = $locale;
        $template->loadFile($file);

        return $template;
    }

    /**
     * Performs the conversion of spreadsheet data according to the template.
     *
     * This method reads rows from the source spreadsheet, processes them according to the column mapping,
     * and writes the results to the template worksheet. It skips rows that should be excluded as defined by
     * the template implementation.
     */
    public function convert(): void
    {
        $rows = $this->prepareRows();

        $i = $this->startingRow;
        foreach ($rows as $sourceRow) {
            if ($this->shouldSkipRow($sourceRow)) {
                continue;
            }

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
            $this->counter++;
        }
    }

    /**
     * Loads the template and source spreadsheet files.
     *
     * This method is responsible for initializing the main and source spreadsheet objects,
     * which will be used for data extraction and transformation during the conversion process.
     */
    public function loadFile(string $file): void
    {
        $this->spreadsheet = IOFactory::load($this->templateFile);
        $this->spreadsheetSource = IOFactory::load($file);
    }

    /**
     * Saves the processed spreadsheet data to a file.
     *
     * After conversion, this method writes the modified spreadsheet to a new file,
     * preserving the structure and data as defined by the template.
     */
    public function saveFile(string $file, string $locale): void
    {
        $path = dirname($file) . '/' . $locale . '_' . basename($file);
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($path);
        $this->writer->addWrittenFile($path);
    }

    /**
     * Converts a value from the source row using custom mapping logic.
     *
     * This method checks if the source column exists in the mapping, and if so,
     * applies a custom converter function if defined for the destination column.
     * This allows for flexible transformation of data during the export process.
     */
    protected function columnConvert(int $col, string $column, array $row): mixed
    {
        if (!array_key_exists($column, $this->sourceColumnMap)) {
            return null;
        }

        $val = $row[$this->sourceColumnMap[$column]];
        $map = $this->columnConvertMap();

        return isset($map[$col]) ? $map[$col]($val, $col, $row) : $val;
    }

    /**
     * Provides a mapping of custom converter functions for specific columns.
     *
     * Child classes must implement this method to define how values are converted
     * for each destination column. The mapping should return callables that process
     * the value, column, and row as needed.
     */
    abstract protected function columnConvertMap(): array;

    /**
     * Allows for the extension of column data type mappings.
     *
     * By default, this mapping includes support for 'ean-{locale}' and 'sku' column types.
     * Implementers can override or extend this mapping to accommodate additional or custom data types as required.
     *
     * @return array<string, \PhpOffice\PhpSpreadsheet\Cell\DataType::<TYPE>> Associative array mapping column names to data types.
     */
    protected function extendDataTypesMap(): array
    {
        return [];
    }

    /**
     * Extracts a value from the source row by column index.
     * If integer is used, assume the index needs to be mapped based on ColumnMap
     *
     * This method uses the column mapping to find the correct source column,
     * then retrieves the corresponding value from the row array.
     */
    protected function extractValueFromSource(int|string $columnIndex, array $row): mixed
    {
        $column = is_int($columnIndex) ? $this->sheetsColumnMap()[$columnIndex] : $columnIndex;

        return $row[$this->sourceColumnMap[$column]];
    }

    /**
     * Prepares and processes rows from the source spreadsheet.
     *
     * This method reads the active sheet, extracts header rows to build a column map,
     * detects currency, resolves the worksheet, and returns the data rows.
     */
    protected function prepareRows(): array
    {
        $rows = $this->spreadsheetSource->getActiveSheet()->toArray();
        $headerRows = array_shift($rows);
        $this->sourceColumnMap = array_flip($headerRows);
        $this->sourceColumnMap[''] = null;

        $this->detectedCurrency = $this->converter->detectCurrency($this->detectedLang);

        $this->resolveWorksheet();

        return $rows;
    }

    /**
     * For "select" (dropdown) attributes, returns a translated value if one exists;
     * if not, returns the original, untranslated value.
     *
     * Allows empty values, as they might be mapped as 0 index or empty default value.
     *
     * This method handles both single and multiple (comma-separated) attribute values,
     * applying mapping logic as needed. It is used to transform attribute data before export.
     */
    protected function selectAttribute(mixed $value, int $col, string $separator = ',')
    {
        if (is_string($value) && str_contains($value, ',')) {
            $attributes = explode(',', $value);
            $attributesLabeled = [];
            foreach ($attributes as $attribute) {
                $attributesLabeled[] = $this->selectAttribute($attribute, $col);
            }

            return implode($separator, $attributesLabeled);
        }

        return $this->getMappedAttribute($col, $value);
    }

    /**
     * Maps source columns to destination columns.
     *
     * Child classes must implement this method to define how source columns
     * are mapped to destination columns in the export template.
     */
    abstract protected function sheetsColumnMap(): array;

    /**
     * Determines whether a row should be skipped during conversion.
     *
     * This method can be overridden by child classes to implement custom logic
     * for excluding certain rows from the export. By default, no rows are skipped.
     */
    protected function shouldSkipRow(array $currentRow): bool
    {
        return false;
    }

    /**
     * Writes a value to the specified cell in the worksheet.
     *
     * This method sets the value and data type (if specified) for the given cell coordinates,
     * ensuring the exported data is formatted correctly according to the template requirements.
     */
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

    /**
     * Determines the data type for a specified column.
     *
     * Child classes must implement this method to specify the data type
     * for each column, which may be used for formatting or validation during export.
     */
    private function getDataType(string $col): ?string
    {
        if (empty($this->dataTypesMap)) {
            $map = [
                'sku' => DataType::TYPE_STRING,
                mb_strtolower('ean-' . $this->detectedLang) => DataType::TYPE_STRING,
            ];

            foreach ($this->extendDataTypesMap() as $name => $type) {
                $map[mb_strtolower($name)] = $type;
            }

            $this->dataTypesMap = $map;
        }

        return $this->dataTypesMap[mb_strtolower($col)] ?? null;
    }

    /**
     * Retrieves a mapped attribute value for a column.
     *
     * This internal method delegates to the converter to map an attribute value
     * for the specified column, using the detected language if available.
     */
    private function getMappedAttribute(int $col, mixed $value): mixed
    {
        $column = $this->sheetsColumnMap()[$col];

        return $this->converter->getMappedAttribute($column, $value, $this->detectedLang);
    }

    /**
     * Resolves and sets the active worksheet for export.
     *
     * This method determines the correct worksheet based on the sheet name or index,
     * ensuring that data is written to the intended location in the export template.
     */
    private function resolveWorksheet(): void
    {
        if (is_int($this->sheetName)) {
            $this->spreadsheet->setActiveSheetIndex($this->sheetName);
        } elseif (is_string($this->sheetName)) {
            $this->spreadsheet->setActiveSheetIndexByName($this->sheetName);
        }

        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }
}
