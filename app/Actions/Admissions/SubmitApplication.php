<?php

namespace App\Actions\Admissions;

use App\Enums\ApplicationStatus;
use App\Jobs\NotifyApplicant;
use App\Models\AcademicYear;
use App\Models\AdmissionWindow;
use App\Models\Application;
use App\Support\Admissions\AgeCheck;
use App\Support\GuardianMatcher;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records an application from the public form. Birth dates outside the
 * grade's range are refused; those inside the exception margin are
 * accepted and flagged for staff. Sibling priority is decided here from
 * the guardian's ID or mobile, not from what the family ticks.
 */
class SubmitApplication
{
    /**
     * @param  array<string, mixed>  $data  validated public form data
     * @return array{0: Application, 1: string} the application and its private-link token
     */
    public function handle(AdmissionWindow $window, array $data): array
    {
        $age = AgeCheck::evaluate($window, CarbonImmutable::parse($data['date_of_birth']));
        if ($age === AgeCheck::OUTSIDE) {
            throw ValidationException::withMessages(['date_of_birth' => __('admissions.age_outside')]);
        }

        if (filled($data['national_id'] ?? null)) {
            $duplicate = Application::query()
                ->where('national_id', $data['national_id'])
                ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn])
                ->whereHas('window', fn ($q) => $q->where('academic_year_id', $window->academic_year_id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['national_id' => __('admissions.duplicate_application')]);
            }
        }

        [$application, $token] = DB::transaction(function () use ($window, $data, $age) {
            // References count per academic year, so serialise on the year.
            AcademicYear::query()->whereKey($window->academic_year_id)->lockForUpdate()->first();
            [$reference, $token] = Application::newIdentifiers($window->academicYear);

            $guardian = GuardianMatcher::find($data['guardian_national_id'] ?? null, $data['guardian_phone']);
            $hasSibling = $guardian !== null && $guardian->students()->exists();

            return [Application::query()->create([
                ...$data,
                'admission_window_id' => $window->id,
                'reference' => $reference,
                'token' => $token,
                'token_hash' => hash('sha256', $token),
                'status' => ApplicationStatus::Submitted,
                'guardian_phone' => PhoneNumber::normalize($data['guardian_phone']),
                'has_sibling' => $hasSibling,
                'age_check' => $age,
                'consented_at' => now(),
                'submitted_at' => now(),
            ]), $token];
        });

        $application->events()->create(['to_status' => ApplicationStatus::Submitted->value]);
        NotifyApplicant::dispatch($application->school_id, $application->id, 'submitted');

        return [$application, $token];
    }
}
