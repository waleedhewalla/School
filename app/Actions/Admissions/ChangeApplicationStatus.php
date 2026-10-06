<?php

namespace App\Actions\Admissions;

use App\Enums\ApplicationStatus;
use App\Jobs\NotifyApplicant;
use App\Models\AdmissionWindow;
use App\Models\Application;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an application along the pipeline, refusing moves the status map
 * does not allow and offers beyond the window's seats. Each move is kept
 * in application_events and the family is told about the ones that
 * concern them.
 */
class ChangeApplicationStatus
{
    /** Moves the family hears about. */
    public const NOTIFY = [
        ApplicationStatus::AssessmentScheduled, ApplicationStatus::Offered, ApplicationStatus::Waitlisted,
        ApplicationStatus::Rejected, ApplicationStatus::Enrolled,
    ];

    public function handle(
        Application $application,
        ApplicationStatus $to,
        ?User $by = null,
        ?string $note = null,
        ?CarbonInterface $assessmentAt = null,
    ): Application {
        DB::transaction(function () use ($application, $to, $by, $note, $assessmentAt) {
            // Lock the window so two offers cannot take the last seat together.
            $window = AdmissionWindow::query()->whereKey($application->admission_window_id)->lockForUpdate()->firstOrFail();
            $application->refresh();
            $from = $application->status;

            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['status' => __('admissions.bad_transition')]);
            }
            if ($to === ApplicationStatus::Offered && $window->seatsLeft() === 0) {
                throw ValidationException::withMessages(['status' => __('admissions.no_seats')]);
            }
            if ($to === ApplicationStatus::AssessmentScheduled && $assessmentAt === null) {
                throw ValidationException::withMessages(['assessment_at' => __('admissions.assessment_time_required')]);
            }

            $application->status = $to;
            if ($assessmentAt !== null) {
                $application->assessment_at = $assessmentAt;
            }
            if (in_array($to, [ApplicationStatus::Offered, ApplicationStatus::Waitlisted, ApplicationStatus::Rejected], true)) {
                $application->decided_at = now();
            }
            $application->save();

            $application->events()->create([
                'user_id' => $by?->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);
        });

        if (in_array($to, self::NOTIFY, true)) {
            NotifyApplicant::dispatch($application->school_id, $application->id, $to->value)->afterCommit();
        }

        return $application;
    }
}
