<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal native Office Open XML writer/reader.
 *
 * Writes single-sheet XLSX workbooks using inline strings and reads back the
 * first worksheet (supporting shared strings, inline strings and raw values).
 * This avoids a spreadsheet dependency for template export / import.
 */
class XlsxService
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const ROOT_RELS = <<<'XML'
    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
        <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>
    XML;

    /**
     * Build a single-sheet XLSX byte string from a header list and an array
     * of rows keyed by the header names.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function build(array $headers, array $rows): string
    {
        return self::buildSheets([
            ['name' => 'Sheet1', 'headers' => $headers, 'rows' => $rows],
        ]);
    }

    /**
     * Build a multi-sheet XLSX byte string.
     *
     * @param  array<int, array{name: string, headers: array<int, string>, rows: array<int, array<string, mixed>>}>  $sheets
     */
    public static function buildSheets(array $sheets): string
    {
        $names = array_map(fn (array $sheet) => self::escapeSheetName($sheet['name']), $sheets);

        $zip = new ZipArchive;
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($tempPath === false || $zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create spreadsheet archive.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($names)));
        $zip->addFromString('_rels/.rels', self::ROOT_RELS);
        $zip->addFromString('xl/workbook.xml', self::workbookXml($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($names)));

        foreach ($sheets as $index => $sheet) {
            $rows = array_map(
                fn (array $row) => array_map(fn (string $header) => $row[$header] ?? '', $sheet['headers']),
                $sheet['rows']
            );

            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                self::worksheetXml([$sheet['headers'], ...$rows])
            );
        }

        $zip->close();

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return $content;
    }

    /**
     * Read the first worksheet of an XLSX file into an array of rows
     * (each row is a list of string cell values).
     *
     * @return array<int, array<int, string>>
     */
    public static function parse(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the file as an Excel workbook.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStrings = self::readSharedStrings($zip->getFromName('xl/sharedStrings.xml'));
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The workbook contains no worksheet.');
        }

        $sheet = simplexml_load_string($sheetXml);

        if ($sheet === false) {
            throw new RuntimeException('The worksheet data could not be read.');
        }

        return self::extractRows($sheet, $sharedStrings);
    }

    private static function contentTypes(int $sheetCount): string
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>

        XML;

        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= "            <Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>\n";
        }

        return $xml."    </Types>\n";
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function workbookXml(array $names): string
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets>

        XML;

        foreach ($names as $index => $name) {
            $xml .= "                <sheet name=\"{$name}\" sheetId=\"".($index + 1).'" r:id="rId'.($index + 1)."\"/>\n";
        }

        return $xml."            </sheets>\n        </workbook>\n";
    }

    private static function workbookRels(int $sheetCount): string
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">

        XML;

        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= "        <Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>\n";
        }

        return $xml.'    </Relationships>'."\n";
    }

    private static function escapeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', trim($name)) ?? $name;

        return mb_substr($name, 0, 31);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function worksheetXml(array $rows): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        $xml .= '<worksheet xmlns="'.self::MAIN_NS.'"><sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $xml .= "<row r=\"{$rowNumber}\">";

            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = self::columnName($columnIndex).$rowNumber;
                $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
                    .self::escape((string) $value)
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @return array<int, string>
     */
    private static function readSharedStrings(string|false $xml): array
    {
        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);

        if ($shared === false) {
            return [];
        }

        $shared->registerXPathNamespace('m', self::MAIN_NS);
        $strings = [];

        foreach ($shared->xpath('//m:si') as $item) {
            $parts = $item->xpath('.//m:t');
            $strings[] = implode('', array_map('strval', $parts));
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private static function extractRows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $sheet->registerXPathNamespace('m', self::MAIN_NS);
        $rows = [];

        foreach ($sheet->xpath('//m:sheetData/m:row') as $row) {
            $row->registerXPathNamespace('m', self::MAIN_NS);
            $cells = [];

            foreach ($row->xpath('m:c') as $cell) {
                $type = (string) $cell['t'];

                if ($type === 's') {
                    $index = (int) (string) $cell->v;
                    $cells[] = $sharedStrings[$index] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cell->registerXPathNamespace('m', self::MAIN_NS);
                    $parts = $cell->xpath('.//m:t');
                    $cells[] = implode('', array_map('strval', $parts));
                } else {
                    $cells[] = trim((string) $cell->v);
                }
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    private static function columnName(int $index): string
    {
        $letters = '';
        $index += 1;

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letters = chr(65 + $modulo).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
