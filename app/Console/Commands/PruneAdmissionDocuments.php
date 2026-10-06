<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * PDPL retention: once an application is closed without enrolment
 * (rejected or withdrawn), its uploaded documents are kept only for a
 * grace period, then deleted. The application row stays, for statistics
 * and to answer the family.
 */
#[Signature('madrasa:prune-admission-documents {--days=90}')]
#[Description('Delete documents of rejected or withdrawn applications closed more than N days ago')]
class PruneAdmissionDocuments extends Command
{
    public function handle(CurrentSchool $currentSchool): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $deleted = 0;

        School::query()->each(function (School $school) use ($currentSchool, $cutoff, &$deleted) {
            $currentSchool->run($school, function () use ($cutoff, &$deleted) {
                ApplicationDocument::query()
                    ->whereHas('application', fn ($q) => $q
                        ->whereIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn])
                        ->where('updated_at', '<', $cutoff))
                    ->each(function (ApplicationDocument $document) use (&$deleted) {
                        Storage::disk('local')->delete($document->path);
                        $document->delete();
                        $deleted++;
                    });
            });
        });

        $this->info("Deleted {$deleted} document(s).");

        return self::SUCCESS;
    }
}
