<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of a .xlsx workbook, or a .csv file, into rows of cell values.
 * Uses PHP's zip and XML extensions only, so no spreadsheet package is needed.
 */
class SpreadsheetReader
{
    // Guards against zip bombs: a parts sheet is far smaller than this once unpacked.
    private const MAX_UNCOMPRESSED_BYTES = 30 * 1024 * 1024;

    /**
     * @return array<int, array<int, string>> rows of cell values, indexed from 0
     */
    public function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx' => $this->readXlsx($path),
            'csv', 'txt' => $this->readCsv($path),
            default => throw new RuntimeException('Upload a .csv or .xlsx file.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($value): string => $this->cleanCsvValue((string) $value), $row);
        }

        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    private function cleanCsvValue(string $value): string
    {
        // Sheets saved by older Excel versions are often Windows-1252 rather than UTF-8.
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The Excel file could not be opened. Save it as .xlsx or .csv and try again.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $this->xmlEntry($zip, $this->firstSheetPath($zip));

            if (! $sheetXml) {
                throw new RuntimeException('The Excel file has no readable worksheet.');
            }

            return $this->sheetRows($sheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xmlEntry($zip, 'xl/workbook.xml');
        $relationships = $this->xmlEntry($zip, 'xl/_rels/workbook.xml.rels');

        if (! $workbook || ! $relationships) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheet = $workbook->xpath('//m:sheets/m:sheet')[0] ?? null;
        $relationshipId = $sheet
            ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']
            : '';

        foreach ($relationships->children() as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $this->xmlEntry($zip, 'xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $strings = [];

        foreach ($xml->children() as $item) {
            $strings[] = $this->inlineText($item);
        }

        return $strings;
    }

    private function sheetRows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $rowIndex = max(0, (int) $row['r'] - 1);
            $cells = [];
            $nextColumn = 0;

            foreach ($row->c as $cell) {
                $column = isset($cell['r']) ? $this->columnIndex((string) $cell['r']) : $nextColumn;
                $cells[$column] = $this->cellValue($cell, $sharedStrings);
                $nextColumn = $column + 1;
            }

            if ($cells === []) {
                continue;
            }

            $rows[$rowIndex] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
        }

        ksort($rows);

        return array_values($rows);
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        $value = match ($type) {
            's' => $sharedStrings[(int) $cell->v] ?? '',
            'inlineStr' => $this->inlineText($cell->is),
            'b' => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
            default => (string) $cell->v,
        };

        return trim($value);
    }

    private function inlineText(?SimpleXMLElement $item): string
    {
        if (! $item) {
            return '';
        }

        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';

        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function columnIndex(string $cellReference): int
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $cellReference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function xmlEntry(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_UNCOMPRESSED_BYTES) {
            throw new RuntimeException('The Excel file is too large to import.');
        }

        $contents = $zip->getFromName($name);

        if ($contents === false) {
            return null;
        }

        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        return $xml === false ? null : $xml;
    }
}
