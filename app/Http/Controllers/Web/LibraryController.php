<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\StaffMember;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** School library: the catalogue, loans to students and staff, returns and overdue books. */
class LibraryController extends Controller
{
    public const LOAN_DAYS = 14;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        return Inertia::render('Library/Index', [
            'books' => LibraryBook::query()->withCount('openLoans')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhere('author', 'like', $like)->orWhere('isbn', $search)->orWhere('category', 'like', $like)))
                ->orderBy('title')->paginate(30)->withQueryString()
                ->through(fn (LibraryBook $b) => $b->only(['id', 'isbn', 'title', 'author', 'publisher', 'published_year', 'category', 'shelf', 'copies'])
                    + ['available' => $b->copies - $b->open_loans_count]),
            'loans' => LibraryLoan::query()->with('book', 'student', 'staffMember')->whereNull('returned_on')
                ->orderBy('due_on')->limit(200)->get()
                ->map(fn (LibraryLoan $l) => [
                    'id' => $l->id, 'book' => $l->book->title, 'borrower' => $l->borrowerName(),
                    'borrowed_on' => $l->borrowed_on->toDateString(), 'due_on' => $l->due_on->toDateString(), 'overdue' => $l->isOverdue(),
                ]),
            'borrowers' => $request->query('borrower') ? $this->borrowers((string) $request->query('borrower')) : [],
            'search' => $search,
            'loanDays' => self::LOAN_DAYS,
        ]);
    }

    public function storeBook(Request $request): RedirectResponse
    {
        LibraryBook::query()->create($this->bookRules($request));

        return back()->with('success', __('Changes saved.'));
    }

    public function updateBook(Request $request, LibraryBook $book): RedirectResponse
    {
        $data = $this->bookRules($request);
        if ($data['copies'] < $book->openLoans()->count()) {
            throw ValidationException::withMessages(['copies' => __('library.copies_on_loan')]);
        }
        $book->update($data);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyBook(LibraryBook $book): RedirectResponse
    {
        if ($book->openLoans()->exists()) {
            return back()->withErrors(['book' => __('library.copies_on_loan')]);
        }
        $book->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function lend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'library_book_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('library_books')],
            'borrower' => ['required', 'string', 'regex:/^(student|staff):\d+$/'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);
        [$type, $id] = explode(':', $data['borrower']);
        $borrower = $type === 'student' ? Student::query()->find($id) : StaffMember::query()->find($id);
        if ($borrower === null) {
            throw ValidationException::withMessages(['borrower' => __('validation.exists', ['attribute' => 'borrower'])]);
        }

        DB::transaction(function () use ($data, $type, $borrower, $request) {
            $book = LibraryBook::query()->whereKey($data['library_book_id'])->lockForUpdate()->firstOrFail();
            if ($book->openLoans()->count() >= $book->copies) {
                throw ValidationException::withMessages(['library_book_id' => __('library.none_available')]);
            }
            LibraryLoan::query()->create([
                'library_book_id' => $book->id,
                'student_id' => $type === 'student' ? $borrower->id : null,
                'staff_member_id' => $type === 'staff' ? $borrower->id : null,
                'borrowed_on' => today(), 'due_on' => $data['due_on'], 'issued_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', __('library.lent'));
    }

    public function return(LibraryLoan $loan): RedirectResponse
    {
        if ($loan->returned_on === null) {
            $loan->update(['returned_on' => today()]);
        }

        return back()->with('success', __('library.returned'));
    }

    /** @return list<array{value: string, label: string}> students and staff matching a name or number */
    private function borrowers(string $term): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $students = Student::query()->search($term)->limit(8)->get()
            ->map(fn (Student $s) => ['value' => "student:{$s->id}", 'label' => $s->name.' ('.$s->student_number.')']);
        $staff = StaffMember::query()->where(fn ($q) => $q->where('name_ar', 'like', $like)->orWhere('employee_number', $term))->limit(5)->get()
            ->map(fn (StaffMember $s) => ['value' => "staff:{$s->id}", 'label' => $s->name.' — '.__('Staff')]);

        return [...$students->all(), ...$staff->all()];
    }

    /** @return array<string, mixed> */
    private function bookRules(Request $request): array
    {
        return $request->validate([
            'isbn' => ['nullable', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'between:1000,'.(now()->year + 1)],
            'category' => ['nullable', 'string', 'max:100'],
            'shelf' => ['nullable', 'string', 'max:50'],
            'copies' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);
    }
}
