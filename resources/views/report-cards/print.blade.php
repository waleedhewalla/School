<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locale::direction() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Report cards') }} — {{ $section->gradeLevel->name }} / {{ $section->name }}</title>
    @if ($inlineCss !== null)
        <style>{!! $inlineCss !!}</style>
    @else
        @vite(['resources/css/print.css'])
    @endif
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">{{ __('Print / save as PDF') }}</button>
    </div>

    @forelse ($results['students'] as $student)
        <article class="card">
            <header>
                <div>
                    <div class="school">{{ $school->name }}</div>
                    @if ($school->ministry_code)
                        <div class="muted">{{ __('Ministry school number') }}: <span dir="ltr">{{ $school->ministry_code }}</span></div>
                    @endif
                </div>
                <div class="title">
                    <h1>{{ __('Report card') }}</h1>
                    <div>{{ $term->name }} — {{ __('Academic year') }} {{ $section->academicYear->name }}</div>
                </div>
            </header>

            <table class="info">
                <tr>
                    <th>{{ __('Student') }}</th><td>{{ $student['name'] }}</td>
                    <th>{{ __('Student number') }}</th><td dir="ltr">{{ $student['student_number'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Class') }}</th><td>{{ $section->gradeLevel->name }} / {{ $section->name }}</td>
                    <th>{{ __('Issued') }}</th><td>{{ $issued }}</td>
                </tr>
            </table>

            <table class="marks">
                <thead>
                    <tr><th>{{ __('Subject') }}</th><th>{{ __('Percentage') }}</th><th>{{ __('Grade') }}</th><th>{{ __('Result') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($results['subjects'] as $subject)
                        @php($r = $student['subjects'][$subject['id']])
                        <tr>
                            <td>{{ $subject['name'] }}</td>
                            <td class="num">{{ $r['complete'] ? number_format($r['percent'], 2).'٪' : '—' }}</td>
                            <td>{{ $r['grade'] ?? '—' }}</td>
                            <td class="{{ $r['complete'] ? ($r['passed'] ? 'pass' : 'fail') : 'muted' }}">
                                {{ $r['complete'] ? ($r['passed'] ? __('Passed') : __('Not passed')) : __('Incomplete') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>{{ __('Overall average') }}</th>
                        <th class="num">{{ $student['average'] !== null ? number_format($student['average'], 2).'٪' : '—' }}</th>
                        <th colspan="2">{{ $student['average_grade'] ?? '' }}</th>
                    </tr>
                </tfoot>
            </table>

            @php($a = $attendance[$student['student_id']])
            <table class="info">
                <tr>
                    <th>{{ __('Days recorded') }}</th><td class="num">{{ $a['recorded'] }}</td>
                    <th>{{ __('Absent') }}</th><td class="num">{{ $a['absent'] }}</td>
                </tr>
                <tr>
                    <th>{{ __('Late') }}</th><td class="num">{{ $a['late'] }}</td>
                    <th>{{ __('Excused absence') }}</th><td class="num">{{ $a['excused'] }}</td>
                </tr>
            </table>

            @if (filled($comments[$student['student_id']] ?? null))
                <div class="comment"><strong>{{ __('Class teacher’s remarks') }}:</strong> {{ $comments[$student['student_id']] }}</div>
            @endif

            <footer>
                <div>{{ __('Class teacher') }}: ....................</div>
                <div>{{ __('Principal') }}: ....................</div>
                <div>{{ __('School stamp') }}</div>
            </footer>
        </article>
    @empty
        <p>{{ __('No students in this section.') }}</p>
    @endforelse
</body>
</html>
