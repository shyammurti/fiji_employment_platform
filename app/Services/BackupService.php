<?php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    public function create(): string
    {
        $disk = Storage::disk('local');
        $name = 'backups/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $tables = DB::transaction(function () {
            $data = [];
            $connection = DB::connection();
            $schema = $connection->getDriverName() === 'mysql' ? $connection->getDatabaseName() : null;
            foreach ($connection->getSchemaBuilder()->getTableListing($schema, schemaQualified: false) as $table) {
                $data[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
            return $data;
        });
        $manifest = ['created_at' => now()->toIso8601String(), 'format' => 1, 'tables' => array_map('count', $tables), 'files' => []];
        $data = Crypt::encryptString(json_encode($tables, JSON_THROW_ON_ERROR));
        if (! $disk->put($name.'/database.enc', $data)) { throw new \RuntimeException('Database backup could not be written.'); }
        $manifest['database_sha256'] = hash('sha256', $data);
        foreach (['credentials', 'resumes', 'job-documents'] as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                $encrypted = Crypt::encryptString($disk->get($file));
                if (! $disk->put($name.'/documents/'.$file.'.enc', $encrypted)) { throw new \RuntimeException('Document backup could not be written.'); }
                $manifest['files'][$file] = hash('sha256', $encrypted);
            }
        }
        if (! $disk->put($name.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) { throw new \RuntimeException('Backup manifest could not be written.'); }
        $this->verify($name);
        return $name;
    }

    public function verify(string $name): array
    {
        if (! preg_match('/^backups\/[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/', $name)) { throw new \InvalidArgumentException('Invalid backup identifier.'); }
        $disk = Storage::disk('local');
        $manifest = json_decode($disk->get($name.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $encrypted = $disk->get($name.'/database.enc');
        if (! hash_equals($manifest['database_sha256'], hash('sha256', $encrypted))) { throw new \RuntimeException('Database backup checksum mismatch.'); }
        $tables = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['tables'] as $table => $count) {
            if (count($tables[$table] ?? []) !== $count) { throw new \RuntimeException('Backup row count mismatch.'); }
        }
        foreach ($manifest['files'] as $file => $hash) {
            if (! preg_match('/^(credentials|resumes|job-documents)\/[a-zA-Z0-9_\/.\- ]+$/', $file) || str_contains($file, '..')) { throw new \RuntimeException('Unsafe backup document path.'); }
            $contents = $disk->get($name.'/documents/'.$file.'.enc');
            if (! hash_equals($hash, hash('sha256', $contents))) { throw new \RuntimeException('Document backup checksum mismatch.'); }
            Crypt::decryptString($contents);
        }
        return ['manifest' => $manifest, 'tables' => $tables];
    }
}
