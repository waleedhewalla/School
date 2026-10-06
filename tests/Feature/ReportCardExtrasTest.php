<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\SchoolRole;
use App\Models\AssessmentComponent;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class ReportCardExtrasTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_homeroom_teacher_comments_and_the_card_shows_attendance(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        [$ahmad] = $d['students'];
        $term = $this->inSchool($school, fn () => Term::query()->first());
        $url = "/report-cards/{$d['sectionA']->id}/{$term->id}";

        // Two absences and one late in term 1 (the seed already has one present day).
        $this->inSchool($school, function () use ($d, $ahmad) {
            foreach (['2026-09-02' => 'A', '2026-09-03' => 'A', '2026-09-06' => 'L'] as $date => $code) {
                app(RecordAttendance::class)->handle($d['sectionA'], Carbon::parse($date), 0, [['student_id' => $ahmad->id, 'code' => $code]], $d['teacher']);
            }
        });

        // Not the class teacher yet.
        $this->actingAs($d['teacher'])->post("$url/comments", ['student_id' => $ahmad->id, 'comment' => 'ممتاز'])->assertForbidden();

        $this->inSchool($school, fn () => TeachingAssignment::query()->where('section_id', $d['sectionA']->id)->update(['is_homeroom' => true]));
        $this->post("$url/comments", ['student_id' => $ahmad->id, 'comment' => 'يحتاج إلى متابعة في الحضور'])->assertSessionHas('success');
        $this->post("$url/comments", ['student_id' => $d['students'][1]->id, 'comment' => ''])->assertSessionHas('success');

        $this->actingAs($this->memberOf($school, SchoolRole::Principal))
            ->get("$url?student_id={$ahmad->id}")
            ->assertOk()
            ->assertSee('يحتاج إلى متابعة في الحضور')
            ->assertSeeInOrder(['أيام الدوام المسجلة', '4', 'غياب', '2', 'تأخر', '1'], false);
    }

    public function test_subjects_follow_the_school_order(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $term = $this->inSchool($school, function () use ($d) {
            $term = Term::query()->first();
            $quran = Subject::query()->create(['code' => 'QURAN', 'sequence' => 1, 'name_ar' => 'القرآن الكريم']);
            $d['subject']->update(['sequence' => 5]);
            AssessmentComponent::query()->create(['term_id' => $term->id, 'grade_level_id' => $d['grade']->id, 'subject_id' => $quran->id, 'name_ar' => 'نهائي', 'max_score' => 100, 'weight' => 100]);

            return $term;
        });

        $this->actingAs($this->memberOf($school, SchoolRole::Principal))
            ->get("/results?term_id={$term->id}&section_id={$d['sectionA']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.subjects.0.name', 'القرآن الكريم')
                ->where('results.subjects.1.name', 'الرياضيات'));
    }

    public function test_dashboard_attendance_rate_and_frequent_absences(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->travelTo('2026-09-20 10:00:00');
        [$ahmad, $sara] = $d['students'];

        $this->inSchool($school, function () use ($d, $ahmad, $sara) {
            foreach (['2026-09-13', '2026-09-14', '2026-09-15'] as $date) {
                app(RecordAttendance::class)->handle($d['sectionA'], Carbon::parse($date), 0, [
                    ['student_id' => $ahmad->id, 'code' => 'A'],
                    ['student_id' => $sara->id, 'code' => 'P'],
                ], $d['teacher']);
            }
        });

        // 7 marks in the last 30 days (seeded 1 present + 6 new): 4 in school.
        $this->actingAs($this->memberOf($school, SchoolRole::Principal))->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendanceRate', 57.1)
                ->has('atRisk', 1)
                ->where('atRisk.0.student_id', $ahmad->id)
                ->where('atRisk.0.absences', 3));
    }
}
