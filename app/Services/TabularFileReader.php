<?php

namespace App\Services;

use SimpleXMLElement;
use ZipArchive;

class TabularFileReader
{
    private const MAX_XML_BYTES = 10_000_000;

    /**
     * @return array<int, array<int, string>>
     */
    public function read(string $path, string $extension): array
    {
        return $extension === 'xlsx'
            ? $this->readXlsx($path)
            : $this->readDelimited($path);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readDelimited(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(
                fn ($value) => is_string($value) ? trim($value) : '',
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $sheetStats = $zip->statName('xl/worksheets/sheet1.xml');

        if (! is_array($sheetStats) || (int) ($sheetStats['size'] ?? 0) > self::MAX_XML_BYTES) {
            $zip->close();

            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);

        if (! $sheet instanceof SimpleXMLElement) {
            return [];
        }

        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $currentRow = [];

            foreach ($row->c as $cell) {
                $index = $this->columnIndex((string) $cell['r']);

                while (count($currentRow) < $index) {
                    $currentRow[] = '';
                }

                $currentRow[] = $this->cellValue($cell, $sharedStrings);
            }

            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $stats = $zip->statName('xl/sharedStrings.xml');

        if (is_array($stats) && (int) ($stats['size'] ?? 0) > self::MAX_XML_BYTES) {
            return [];
        }

        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $sharedStrings = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if (! $sharedStrings instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($sharedStrings->si as $item) {
            if (isset($item->t)) {
                $strings[] = trim((string) $item->t);

                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            $strings[] = trim($text);
        }

        return $strings;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $index = 0;

        foreach (str_split(strtoupper($matches[0] ?? 'A')) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text = '';

            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            return trim($text);
        }

        $value = trim((string) ($cell->v ?? ''));

        return $type === 's'
            ? trim((string) ($sharedStrings[(int) $value] ?? ''))
            : $value;
    }
}
