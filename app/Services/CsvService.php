<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal RFC 4180 CSV writer for feature exports.
 */
class CsvService
{
    /**
     * Build a CSV string from a header list and rows keyed by header names.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function build(array $headers, array $rows): string
    {
        $lines = [self::line($headers)];

        foreach ($rows as $row) {
            $lines[] = self::line(array_map(fn (string $header) => $row[$header] ?? '', $headers));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function line(array $values): string
    {
        return implode(',', array_map(
            static fn (mixed $value) => '"'.str_replace('"', '""', (string) $value).'"',
            $values
        ));
    }
}
