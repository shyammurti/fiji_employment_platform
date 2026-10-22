<?php
namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653).
 */
class RestoreCheck extends Command
{
    protected $signature = 'backup:restore-check {snapshot : The backups/date-id returned by backup:run}';
    protected $description = 'Restore a backup into a NEW isolated SQLite recovery database and compare all rows';

    public function handle(BackupService $backups): int
    {
        if (! extension_loaded('pdo_sqlite')) { $this->error('Enable pdo_sqlite for the isolated recovery check.'); return self::FAILURE; }
        $backup = $backups->verify($this->argument('snapshot'));
        $disk = Storage::disk('local');
        $root = 'recovery/check-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $disk->makeDirectory($root);
        $path = $disk->path($root.'/database.sqlite');
        $handle = fopen($path, 'x'); fclose($handle);
        config(['database.connections.recovery' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('recovery');
        Artisan::call('migrate', ['--database' => 'recovery', '--force' => true]);
        $connection = DB::connection('recovery');
        $connection->getSchemaBuilder()->disableForeignKeyConstraints();
        try {
            $connection->transaction(function () use ($connection, $backup) {
                foreach ($backup['tables'] as $table => $rows) {
                    if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! $connection->getSchemaBuilder()->hasTable($table)) { throw new \RuntimeException('Backup schema differs from this application.'); }
                    $connection->table($table)->delete();
                    foreach (array_chunk($rows, 50) as $chunk) { $connection->table($table)->insert($chunk); }
                }
            });
        } finally { $connection->getSchemaBuilder()->enableForeignKeyConstraints(); }
        $counts = [];
        foreach ($backup['tables'] as $table => $rows) {
            $actual = $connection->table($table)->get()->map(fn ($row) => (array) $row)->all();
            $normalize = function ($items) {
                $result = array_map(function ($row) { ksort($row); return json_encode(array_map(fn ($v) => $v === null ? null : (string) $v, $row), JSON_THROW_ON_ERROR); }, $items);
                sort($result); return $result;
            };
            if ($normalize($actual) !== $normalize($rows)) { throw new \RuntimeException('Recovery comparison failed for '.$table); }
            $counts[$table] = count($actual);
        }
        if ($connection->select('PRAGMA foreign_key_check')) { throw new \RuntimeException('Recovered foreign keys are inconsistent.'); }
        foreach ($backup['manifest']['files'] as $file => $hash) {
            $contents = Crypt::decryptString($disk->get($this->argument('snapshot').'/documents/'.$file.'.enc'));
            if (! $disk->put($root.'/documents/'.$file, $contents) || $disk->get($root.'/documents/'.$file) !== $contents) { throw new \RuntimeException('Document restore failed.'); }
        }
        DB::disconnect('recovery');
        $reopened = DB::connection('recovery')->table('users')->count();
        if ($reopened !== ($counts['users'] ?? 0)) { throw new \RuntimeException('Reopened recovery database differs.'); }
        $disk->put($root.'/verification.json', json_encode(['tables' => $counts, 'documents' => count($backup['manifest']['files']), 'all_rows_equal' => true, 'reopened_successfully' => true], JSON_PRETTY_PRINT));
        $this->info('Restored and verified all rows and documents in '.$root.'. The working database was not replaced.');
        return self::SUCCESS;
    }
}
