<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Deletes uploaded import files nobody confirmed (they hold students' personal data). */
#[Signature('madrasa:prune-imports {--hours=24}')]
#[Description('Delete unconfirmed student import uploads older than N hours')]
class PruneImports extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('imports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} file(s).");

        return self::SUCCESS;
    }
}
