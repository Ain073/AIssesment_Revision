<?php

namespace Tests\Unit;

use App\Services\TabularFileReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class TabularFileReaderTest extends TestCase
{
    public function test_it_reads_and_trims_csv_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'student-import-');
        file_put_contents($path, "name, student_number\nJuan Dela Cruz, 2024-00001\n");

        try {
            $rows = (new TabularFileReader)->read($path, 'csv');
        } finally {
            @unlink($path);
        }

        $this->assertSame([
            ['name', 'student_number'],
            ['Juan Dela Cruz', '2024-00001'],
        ], $rows);
    }

    public function test_it_reads_inline_strings_from_the_first_xlsx_sheet(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'student-import-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetData>
                    <row r="1">
                        <c r="A1" t="inlineStr"><is><t>name</t></is></c>
                        <c r="C1" t="inlineStr"><is><t>student_number</t></is></c>
                    </row>
                    <row r="2">
                        <c r="A2" t="inlineStr"><is><t>Juan Dela Cruz</t></is></c>
                        <c r="C2" t="inlineStr"><is><t>2024-00001</t></is></c>
                    </row>
                </sheetData>
            </worksheet>
            XML);
        $zip->close();

        try {
            $rows = (new TabularFileReader)->read($path, 'xlsx');
        } finally {
            @unlink($path);
        }

        $this->assertSame([
            ['name', '', 'student_number'],
            ['Juan Dela Cruz', '', '2024-00001'],
        ], $rows);
    }

    public function test_it_reads_shared_strings_from_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'student-import-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <si><t>student_number</t></si>
                <si><t>2024-00001</t></si>
            </sst>
            XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                <sheetData>
                    <row r="1"><c r="B1" t="s"><v>0</v></c></row>
                    <row r="2"><c r="B2" t="s"><v>1</v></c></row>
                </sheetData>
            </worksheet>
            XML);
        $zip->close();

        try {
            $rows = (new TabularFileReader)->read($path, 'xlsx');
        } finally {
            @unlink($path);
        }

        $this->assertSame([
            ['', 'student_number'],
            ['', '2024-00001'],
        ], $rows);
    }
}
