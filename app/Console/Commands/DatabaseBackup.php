<?php
namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653).
 */
class DatabaseBackup extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Create and verify an encrypted database and document backup';
    public function handle(BackupService $backups): int
    {
        $name = $backups->create();
        $this->info('Verified encrypted backup: '.$name);
        $this->line('Keep the application key safely; it is required to decrypt this backup.');
        return self::SUCCESS;
    }
}
