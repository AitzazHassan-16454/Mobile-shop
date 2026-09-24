<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function download(Request $request, string $currentTeam): BinaryFileResponse
    {
        File::ensureDirectoryExists(storage_path('app/backups'));

        $timestamp = now()->format('Y_m_d_His');
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            $backupFileName = 'horizon_studio_backup_'.$timestamp.'.sqlite';
            $tempPath = storage_path('app/backups/'.$backupFileName);

            if (File::exists($dbPath) && is_file($dbPath)) {
                File::copy($dbPath, $tempPath);
            } else {
                File::put($tempPath, '-- Horizon Studio SQLite Backup --');
            }

            return response()->download($tempPath, $backupFileName, [
                'Content-Type' => 'application/x-sqlite3',
            ])->deleteFileAfterSend(true);
        }

        // MySQL / MariaDB / Standard SQL Export
        $backupFileName = 'horizon_studio_backup_'.$timestamp.'.sql';
        $tempPath = storage_path('app/backups/'.$backupFileName);

        $tables = Schema::getTableListing();
        $sqlDump = "-- Horizon Studio Database Backup\n";
        $sqlDump .= "-- Generated: ".now()->toDateTimeString()."\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Get Create Table statement if MySQL
            try {
                $createTableResult = DB::select("SHOW CREATE TABLE `{$table}`");
                if (!empty($createTableResult)) {
                    $createTableArray = (array) $createTableResult[0];
                    $createSql = $createTableArray['Create Table'] ?? array_values($createTableArray)[1] ?? null;
                    if ($createSql) {
                        $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                        $sqlDump .= $createSql . ";\n\n";
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if view or not supported
            }

            // Get Rows
            $rows = DB::table($table)->get();
            if ($rows->count() > 0) {
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = array_keys($rowArray);
                    $escapedColumns = array_map(fn ($col) => "`$col`", $columns);
                    $escapedValues = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        return DB::getPdo()->quote((string) $val);
                    }, array_values($rowArray));

                    $sqlDump .= "INSERT INTO `{$table}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                }
                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        File::put($tempPath, $sqlDump);

        return response()->download($tempPath, $backupFileName, [
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(true);
    }
}
