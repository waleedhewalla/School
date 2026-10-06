<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_contract_run_adjust_approve_export_and_payslip(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $accountant = $this->memberOf($school, SchoolRole::Accountant);
        $expat = $this->inSchool($school, fn () => StaffMember::query()->create(['employee_number' => 'T2', 'name_ar' => 'أ. أحمد']));
        $this->actingAs($accountant);

        // Saudi teacher: basic 8000 + housing 2000 → GOSI wage 10,000: 975 staff, 1,175 school.
        $this->put("/payroll/contracts/{$d['staff']->id}", ['is_saudi' => true, 'basic_salary' => 8000, 'housing_allowance' => 2000,
            'transport_allowance' => 800, 'gosi_registered' => true, 'iban' => 'SA0380000000608010167519'])->assertSessionHasNoErrors();
        $this->put("/payroll/contracts/{$expat->id}", ['is_saudi' => false, 'basic_salary' => 5000, 'housing_allowance' => 1250, 'gosi_registered' => true, 'iban' => 'SA12'])
            ->assertSessionHasErrors('iban');
        $this->put("/payroll/contracts/{$expat->id}", ['is_saudi' => false, 'basic_salary' => 5000, 'housing_allowance' => 1250, 'gosi_registered' => true])->assertSessionHasNoErrors();

        $this->post('/payroll/runs', ['month' => '2026-09'])->assertRedirect();
        $this->post('/payroll/runs', ['month' => '2026-09'])->assertSessionHasErrors('month');
        $run = $this->inSchool($school, fn () => PayrollRun::query()->sole());
        [$saudi, $other] = $this->inSchool($school, fn () => [
            PayrollLine::query()->where('staff_member_id', $d['staff']->id)->sole(),
            PayrollLine::query()->where('staff_member_id', $expat->id)->sole(),
        ]);
        $this->assertSame('975.00', $saudi->gosi_employee);
        $this->assertSame('1175.00', $saudi->gosi_employer);
        $this->assertSame('9825.00', $saudi->net); // 10,800 − 975
        $this->assertSame('0.00', $other->gosi_employee);
        $this->assertSame('125.00', $other->gosi_employer); // 2% of 6,250

        $this->patch("/payroll/lines/{$saudi->id}", ['additions' => 500, 'deductions' => 300, 'note' => 'غياب يوم'])->assertSessionHasNoErrors();
        $this->assertSame('10025.00', $this->inSchool($school, fn () => $saudi->refresh()->net));

        // The teacher sees nothing until the month is approved.
        $this->actingAs($d['teacher'])->get('/my/payslips')->assertInertia(fn (Assert $page) => $page->has('payslips', 0));
        $this->get('/payroll')->assertForbidden();

        $this->actingAs($accountant);
        $this->post("/payroll/runs/{$run->id}/approve")->assertSessionHasNoErrors();
        $this->patch("/payroll/lines/{$saudi->id}", ['additions' => 0, 'deductions' => 0])->assertSessionHasErrors('additions');
        $this->get("/payroll/runs/{$run->id}/export")->assertOk()->assertDownload('payroll-2026-09.xlsx');

        $this->actingAs($d['teacher'])->get('/my/payslips')->assertInertia(fn (Assert $page) => $page->has('payslips', 1)->where('payslips.0.net', '10025.00'));
    }

    public function test_principal_cannot_see_payroll_by_default(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::Principal))->get('/payroll')->assertForbidden();
    }
}
