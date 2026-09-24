<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Modern native Office Open XML writer/reader.
 *
 * Writes styled XLSX workbooks with headers, auto column widths, zebra striping,
 * and cell alignments using inline strings and styles without external dependencies.
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
        $zip->addFromString('xl/styles.xml', self::stylesXml());

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
            <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>

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

        $xml .= "        <Relationship Id=\"rIdStyles\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>\n";

        return $xml.'    </Relationships>'."\n";
    }

    private static function stylesXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <fonts count="4">
                <font>
                    <sz val="10"/>
                    <color rgb="FF0F172A"/>
                    <name val="Segoe UI"/>
                </font>
                <font>
                    <b/>
                    <sz val="11"/>
                    <color rgb="FFFFFFFF"/>
                    <name val="Segoe UI"/>
                </font>
                <font>
                    <b/>
                    <sz val="12"/>
                    <color rgb="FF003B7D"/>
                    <name val="Segoe UI"/>
                </font>
                <font>
                    <i/>
                    <sz val="10"/>
                    <color rgb="FF475569"/>
                    <name val="Segoe UI"/>
                </font>
            </fonts>
            <fills count="6">
                <fill><patternFill patternType="none"/></fill>
                <fill><patternFill patternType="gray125"/></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FF003B7D"/><bgColor indexed="64"/></patternFill></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FFE0F2FE"/><bgColor indexed="64"/></patternFill></fill>
                <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>
            </fills>
            <borders count="2">
                <border><left/><right/><top/><bottom/></border>
                <border>
                    <left style="thin"><color rgb="E2E8F0"/></left>
                    <right style="thin"><color rgb="E2E8F0"/></right>
                    <top style="thin"><color rgb="E2E8F0"/></top>
                    <bottom style="thin"><color rgb="CBD5E1"/></bottom>
                </border>
            </borders>
            <cellStyleXfs count="1">
                <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
            </cellStyleXfs>
            <cellXfs count="7">
                <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="left" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="left" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="right" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="left" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="right" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="right" vertical="center"/>
                </xf>
                <xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
                    <alignment horizontal="left" vertical="center"/>
                </xf>
            </cellXfs>
        </styleSheet>
        XML;
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
        $xml .= '<worksheet xmlns="'.self::MAIN_NS.'">';
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane showGridLines="1"/></sheetView></sheetViews>';

        $columnWidths = [];
        foreach ($rows as $cells) {
            foreach (array_values($cells) as $colIndex => $value) {
                $len = mb_strlen((string) $value);
                $columnWidths[$colIndex] = max($columnWidths[$colIndex] ?? 0, $len);
            }
        }

        if (! empty($columnWidths)) {
            $xml .= '<cols>';
            foreach ($columnWidths as $colIndex => $maxLen) {
                $colNum = $colIndex + 1;
                $width = min(55, max(14, $maxLen + 5));
                $xml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$width}\" customWidth=\"1\"/>";
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $isHeader = ($rowIndex === 0);
            $height = $isHeader ? 28 : 22;
            $xml .= "<row r=\"{$rowNumber}\" ht=\"{$height}\" customHeight=\"1\">";

            $firstCellVal = trim((string) reset($cells));
            $isCommentRow = str_starts_with($firstCellVal, '#');
            $isEvenDataRow = ($rowIndex % 2 === 0);

            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = self::columnName($columnIndex).$rowNumber;
                $strVal = (string) $value;
                $isNumeric = self::isNumericValue($strVal);

                if ($isHeader) {
                    $styleId = $isNumeric ? 2 : 1;
                } elseif ($isCommentRow) {
                    $styleId = 6;
                } elseif ($isEvenDataRow) {
                    $styleId = $isNumeric ? 5 : 3;
                } else {
                    $styleId = $isNumeric ? 4 : 0;
                }

                $xml .= '<c r="'.$reference.'" t="inlineStr" s="'.$styleId.'"><is><t xml:space="preserve">'
                    .self::escape($strVal)
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function isNumericValue(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                return false;
            }

            return is_numeric($trimmed) || (bool) preg_match('/^-?\$?\d+([.,]\d+)?%?$/', $trimmed);
        }

        return false;
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
