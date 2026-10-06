<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Requests the main pages as admin, teacher and parent of a demo school and
 * prints response time and query count, to catch slow pages and N+1
 * queries before pilots. Run after `madrasa:demo-large`.
 */
#[Signature('madrasa:profile {--slug=large} {--runs=3}')]
#[Description('Time the main pages of a demo school (development only)')]
class ProfilePages extends Command
{
    public function handle(Kernel $kernel): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $school = School::query()->where('slug', $this->option('slug'))->firstOrFail();
        [$section, $term] = app(CurrentSchool::class)->run($school, fn () => [
            Section::query()->orderBy('id')->first(),
            Term::query()->orderBy('id')->first(),
        ]);
        $today = '2026-10-01';

        $pages = [
            'large-admin@example.com' => [
                '/dashboard', '/students', '/students?search=العتيبي', "/students?section_id={$section->id}",
                "/attendance?section_id={$section->id}&date={$today}", "/results?term_id={$term->id}&section_id={$section->id}",
                "/report-cards/{$section->id}/{$term->id}", "/marks?term_id={$term->id}", "/timetable?section_id={$section->id}",
                '/announcements', '/users', '/api/v1/students?per_page=50',
            ],
            'large-teacher0@example.com' => [
                '/dashboard', "/marks?term_id={$term->id}&section_id={$section->id}&subject_id=".DB::table('subjects')->where('school_id', $school->id)->where('code', 'MATH')->value('id'),
                "/attendance?section_id={$section->id}&date={$today}", '/my/timetable',
            ],
            'large-parent@example.com' => ['/my/children'],
        ];

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $rows = [];
        foreach ($pages as $email => $urls) {
            $user = User::query()->where('email', $email)->firstOrFail();
            foreach ($urls as $url) {
                $times = [];
                $queries = 0;
                for ($i = 0; $i < (int) $this->option('runs'); $i++) {
                    $count = 0;
                    Auth::guard('web')->setUser($user);
                    Auth::guard('sanctum')->setUser($user);

                    $request = Request::create($url, 'GET', server: ['HTTP_ACCEPT' => str_starts_with($url, '/api') ? 'application/json' : 'text/html']);
                    $request->headers->set('X-School', $school->slug);
                    $started = microtime(true);
                    $response = $kernel->handle($request);
                    $times[] = (microtime(true) - $started) * 1000;
                    $kernel->terminate($request, $response);
                    $queries = $count;
                    $status = $response->getStatusCode();
                    app()->forgetScopedInstances();
                    app(CurrentSchool::class)->forget();
                }
                sort($times);
                $rows[] = [explode('@', $email)[0], $url, $status, round($times[intdiv(count($times), 2)]), $queries];
            }
        }

        $this->table(['user', 'page', 'status', 'median ms', 'queries'], $rows);

        return self::SUCCESS;
    }
}
