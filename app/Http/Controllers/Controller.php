<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

abstract class Controller
{
    /**
     * Build a file-download response for a rendered export.
     */
    protected function downloadExport(string $content, string $slug, string $format): Response
    {
        $type = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $filename = "{$slug}-".now()->format('Y-m-d').".{$extension}";

        return response($content, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
