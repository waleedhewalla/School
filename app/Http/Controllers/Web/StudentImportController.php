<?php

namespace App\Http\Controllers\Web;

use App\Actions\Students\ImportStudents;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Import\NoorStudentSheet;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Upload → preview (nothing saved) → confirm. The uploaded file is kept
 * in private storage between the two steps and deleted after import.
 */
class StudentImportController extends Controller
{
    /** Larger schools import one stage at a time. */
    public const MAX_ROWS = 2000;

    /** The upload form, or the preview of an uploaded file waiting for confirmation. */
    public function create(Request $request): Response
    {
        $pending = $request->session()->get('student_import');

        return Inertia::render('Students/Import', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'report' => $pending['report'] ?? null,
            'year' => $pending ? AcademicYear::query()->find($pending['year'])?->only(['id', 'name']) : null,
        ]);
    }

    public function preview(Request $request, ImportStudents $import, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'academic_year_id' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
        ]);

        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs($this->dir($currentSchool), $token.'.xlsx', 'local');
        try {
            $sheet = NoorStudentSheet::read(Storage::disk('local')->path($path), self::MAX_ROWS + 1);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e);
            throw ValidationException::withMessages(['file' => __('import.unreadable')]);
        }

        if (! isset($sheet['columns']['full_name_ar'])) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => __('import.no_columns')]);
        }
        if (count($sheet['rows']) > self::MAX_ROWS) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file' => __('import.too_many_rows', ['max' => self::MAX_ROWS])]);
        }

        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        $this->discardPending($request, $currentSchool);
        $request->session()->put('student_import', [
            'token' => $token,
            'year' => $year->id,
            'report' => $import->handle($sheet['rows'], $year, commit: false),
        ]);

        return redirect()->route('students.import');
    }

    /** Cancel a previewed import. */
    public function destroy(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $this->discardPending($request, $currentSchool);

        return redirect()->route('students.import');
    }

    private function discardPending(Request $request, CurrentSchool $currentSchool): void
    {
        if ($pending = $request->session()->pull('student_import')) {
            Storage::disk('local')->delete($this->dir($currentSchool).'/'.$pending['token'].'.xlsx');
        }
    }

    public function store(Request $request, ImportStudents $import, CurrentSchool $currentSchool): RedirectResponse
    {
        $pending = $request->session()->pull('student_import');
        $path = $pending ? $this->dir($currentSchool).'/'.$pending['token'].'.xlsx' : null;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 410);

        $year = AcademicYear::query()->findOrFail($pending['year']);
        $report = $import->handle(NoorStudentSheet::read(Storage::disk('local')->path($path))['rows'], $year, commit: true);
        Storage::disk('local')->delete($path);

        $imported = collect($report)->where('status', 'imported')->count();

        return redirect()->route('students.index')->with('success', __('Imported :count students.', ['count' => $imported]));
    }

    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        NoorStudentSheet::writeTemplate($path);

        return response()->download($path, 'students-template.xlsx')->deleteFileAfterSend();
    }

    private function dir(CurrentSchool $currentSchool): string
    {
        return 'imports/'.$currentSchool->id();
    }
}
