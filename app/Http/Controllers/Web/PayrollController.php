<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Support\Export\Spreadsheet;
use App\Support\Payroll\PayrollCalculator;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Staff pay: contracts, monthly payroll runs with GOSI contributions and
 * per-person adjustments, approval, a register for the bank / Mudad (WPS)
 * upload, and payslips that staff see once a month is approved.
 */
class PayrollController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Payroll/Index', [
            'runs' => PayrollRun::query()->withCount('lines')->withSum('lines', 'net')->orderByDesc('month')->get()
                ->map(fn (PayrollRun $r) => ['id' => $r->id, 'month' => $r->month, 'status' => $r->status, 'lines' => $r->lines_count, 'net' => round((float) $r->lines_sum_net, 2)]),
            'staff' => StaffMember::query()->with('contract')->where('status', 'active')->orderBy('name_ar')->get()
                ->map(fn (StaffMember $s) => [
                    'id' => $s->id, 'name' => $s->name, 'employee_number' => $s->employee_number, 'job_title' => $s->job_title,
                    'contract' => $s->contract ? [
                        ...$s->contract->only(['is_saudi', 'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances', 'gosi_registered', 'bank']),
                        'iban' => $s->contract->iban, 'hired_on' => $s->contract->hired_on?->toDateString(),
                    ] : null,
                ]),
            'suggestedMonth' => now()->format('Y-m'),
        ]);
    }

    public function saveContract(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $data = $request->validate([
            'is_saudi' => ['required', 'boolean'],
            'hired_on' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'other_allowances' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'gosi_registered' => ['required', 'boolean'],
            'bank' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'regex:/^SA\d{22}$/'],
        ], ['iban.regex' => __('payroll.iban')]);
        if ($staffMember->user_id !== null && (int) $staffMember->user_id === (int) $request->user()->id) {
            throw ValidationException::withMessages(['basic_salary' => __('payroll.own_contract')]);
        }
        foreach (['housing_allowance', 'transport_allowance', 'other_allowances'] as $key) {
            $data[$key] ??= 0;
        }
        StaffContract::query()->updateOrCreate(['staff_member_id' => $staffMember->id], $data);

        return back()->with('success', __('Changes saved.'));
    }

    public function createRun(Request $request): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m',
            Rule::unique('payroll_runs', 'month')->where('school_id', app(CurrentSchool::class)->id())]]);

        $run = DB::transaction(function () use ($data, $request) {
            $run = PayrollRun::query()->create(['month' => $data['month'], 'status' => PayrollRun::DRAFT, 'created_by' => $request->user()->id]);
            StaffContract::query()->whereHas('staffMember', fn ($q) => $q->where('status', 'active'))->each(
                fn (StaffContract $c) => $run->lines()->create(['staff_member_id' => $c->staff_member_id] + PayrollCalculator::lineFor($c)),
            );

            return $run;
        });

        return redirect()->route('payroll.run', $run);
    }

    public function run(PayrollRun $run): Response
    {
        return Inertia::render('Payroll/Run', [
            'run' => $run->only(['id', 'month', 'status']) + ['approved_at' => $run->approved_at?->toDateString()],
            'lines' => $run->lines()->with('staffMember')->get()->sortBy(fn (PayrollLine $l) => $l->staffMember->name_ar)->values()
                ->map(fn (PayrollLine $l) => [
                    ...$l->only(['id', 'basic', 'housing', 'transport', 'other', 'additions', 'deductions', 'note', 'gosi_employee', 'gosi_employer', 'net']),
                    'name' => $l->staffMember->name, 'employee_number' => $l->staffMember->employee_number, 'gross' => $l->gross(),
                ]),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line): RedirectResponse
    {
        if (! $line->run->isDraft()) {
            throw ValidationException::withMessages(['additions' => __('payroll.locked')]);
        }
        $line->fill($request->validate([
            'additions' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'deductions' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]));
        $line->net = PayrollCalculator::net($line);
        $line->save();

        return back()->with('success', __('Changes saved.'));
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        if (! $run->isDraft()) {
            throw ValidationException::withMessages(['run' => __('payroll.locked')]);
        }
        if ((int) $run->created_by === (int) $request->user()->id) {
            throw ValidationException::withMessages(['run' => __('payroll.four_eyes')]);
        }
        if ($run->lines()->where('net', '<', 0)->exists()) {
            throw ValidationException::withMessages(['run' => __('payroll.negative_net')]);
        }
        $run->update(['status' => PayrollRun::APPROVED, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        activity()->causedBy($request->user())->performedOn($run)->log('payroll approved');

        return back()->with('success', __('payroll.approved'));
    }

    public function destroyRun(PayrollRun $run): RedirectResponse
    {
        abort_unless($run->isDraft(), 422);
        $run->delete();

        return redirect()->route('payroll.index')->with('success', __('Deleted.'));
    }

    /** Payroll register for the bank transfer / Mudad (WPS) upload. */
    public function export(Request $request, PayrollRun $run): BinaryFileResponse
    {
        $rows = $run->lines()->with('staffMember.contract')->get()->sortBy(fn (PayrollLine $l) => $l->staffMember->employee_number)
            ->map(fn (PayrollLine $l) => [
                $l->staffMember->employee_number, $l->staffMember->name_ar, $l->staffMember->national_id,
                $l->staffMember->contract?->bank, $l->staffMember->contract?->iban,
                (float) $l->basic, (float) $l->housing, (float) $l->transport + (float) $l->other + (float) $l->additions,
                (float) $l->deductions + (float) $l->gosi_employee, (float) $l->net, (float) $l->gosi_employer,
            ]);
        activity()->causedBy($request->user())->performedOn($run)->log('payroll exported');

        return Spreadsheet::download("payroll-{$run->month}.xlsx", [$run->month => [[
            'الرقم الوظيفي', 'الاسم', 'رقم الهوية/الإقامة', 'البنك', 'الآيبان', 'الراتب الأساسي', 'بدل السكن', 'بدلات أخرى',
            'الحسومات', 'صافي الراتب', 'حصة المنشأة في التأمينات',
        ], $rows]]);
    }

    /** Staff see their own payslips for approved months. */
    public function myPayslips(Request $request): Response
    {
        $staff = StaffMember::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Payroll/MyPayslips', [
            'payslips' => $staff ? PayrollLine::query()->with('run')->where('staff_member_id', $staff->id)
                ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::APPROVED))->get()
                ->sortByDesc(fn (PayrollLine $l) => $l->run->month)->values()
                ->map(fn (PayrollLine $l) => [
                    ...$l->only(['id', 'basic', 'housing', 'transport', 'other', 'additions', 'deductions', 'note', 'gosi_employee', 'net']),
                    'month' => $l->run->month, 'gross' => $l->gross(),
                ]) : [],
            'name' => $staff?->name,
        ]);
    }
}
