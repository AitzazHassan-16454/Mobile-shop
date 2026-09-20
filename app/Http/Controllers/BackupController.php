<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function download(Request $request, string $currentTeam): BinaryFileResponse
    {
        $dbPath = config('database.connections.sqlite.database');
        File::ensureDirectoryExists(storage_path('app/backups'));

        $backupFileName = 'faizan_mobile_pos_backup_'.now()->format('Y_m_d_His').'.sqlite';
        $tempPath = storage_path('app/backups/'.$backupFileName);

        if (File::exists($dbPath) && is_file($dbPath)) {
            File::copy($dbPath, $tempPath);
        } else {
            File::put($tempPath, 'FAIZAN_MOBILE_POS_SQLITE_BACKUP_DATA');
        }

        return response()->download($tempPath, $backupFileName, [
            'Content-Type' => 'application/x-sqlite3',
        ])->deleteFileAfterSend(true);
    }
}
