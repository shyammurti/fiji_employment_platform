<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Module M1 Jobseeker Profile & Verification, developed by Sameet Singh (S11231364).
 */
class PrivatizeDocuments extends Command
{
    protected $signature = 'documents:privatize';
    protected $description = 'Move credentials and resumes to private storage after verifying each copy';

    public function handle(): int
    {
        $private = Storage::disk('local');
        $count = 0;
        foreach (['public', 'legacy_public'] as $source) {
        $public = Storage::disk($source);
        foreach (['credentials', 'resumes'] as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                $contents = $public->get($path);
                if ($private->exists($path) && hash('sha256', $private->get($path)) !== hash('sha256', $contents)) {
                    $this->error('Conflicting private file: '.$path.'; original retained.');
                    return self::FAILURE;
                }
                if (! $private->put($path, $contents) || hash('sha256', $private->get($path)) !== hash('sha256', $contents)) {
                    $this->error('Copy verification failed; original retained.');
                    return self::FAILURE;
                }
                if (! $public->delete($path)) {
                    $this->error('Private copy verified, but public copy could not be removed.');
                    return self::FAILURE;
                }
                $count++;
            }
        }
        }
        $this->info("Verified and moved {$count} documents to private storage.");
        return self::SUCCESS;
    }
}
