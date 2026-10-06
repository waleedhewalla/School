<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** School buses: buses and their crews, routes with stops, and which students ride where. */
class TransportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Transport/Index', [
            'buses' => Bus::query()->orderBy('number')->get()->map(fn (Bus $b) => $b->only([
                'id', 'number', 'plate', 'capacity', 'driver_name', 'driver_phone', 'supervisor_name', 'supervisor_phone',
            ])),
            'routes' => BusRoute::query()->with('bus')->withCount(['riders', 'stops'])->orderBy('name')->get()
                ->map(fn (BusRoute $r) => [
                    'id' => $r->id, 'name' => $r->name, 'bus_id' => $r->bus_id, 'bus' => $r->bus?->number,
                    'capacity' => $r->bus?->capacity, 'riders' => $r->riders_count, 'stops' => $r->stops_count,
                ]),
        ]);
    }

    public function saveBus(Request $request, ?Bus $bus = null): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20'],
            'plate' => ['nullable', 'string', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'supervisor_name' => ['nullable', 'string', 'max:150'],
            'supervisor_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);
        $bus ? $bus->update($data) : Bus::query()->create($data);

        return back()->with('success', __('Changes saved.'));
    }

    public function saveRoute(Request $request, ?BusRoute $route = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'bus_id' => ['nullable', 'integer', ExistsInCurrentSchool::inTable('buses')],
        ]);
        $route ? $route->update($data) : $route = BusRoute::query()->create($data);

        return redirect()->route('transport.route', $route)->with('success', __('Changes saved.'));
    }

    public function destroyRoute(BusRoute $route): RedirectResponse
    {
        if ($route->riders()->exists()) {
            return back()->withErrors(['route' => __('transport.route_has_riders')]);
        }
        $route->delete();

        return redirect()->route('transport.index')->with('success', __('Deleted.'));
    }

    public function route(Request $request, BusRoute $route): Response
    {
        $route->load('bus', 'stops');
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Transport/Route', [
            'route' => [
                'id' => $route->id, 'name' => $route->name, 'bus_id' => $route->bus_id,
                'bus' => $route->bus?->only(['number', 'plate', 'capacity', 'driver_name', 'driver_phone', 'supervisor_name', 'supervisor_phone']),
            ],
            'stops' => $route->stops->map(fn (RouteStop $s) => [
                'id' => $s->id, 'name' => $s->name, 'sequence' => $s->sequence,
                'pickup_at' => $s->pickup_at ? substr($s->pickup_at, 0, 5) : null, 'dropoff_at' => $s->dropoff_at ? substr($s->dropoff_at, 0, 5) : null,
            ]),
            'riders' => StudentTransport::query()->with('student.currentEnrollment.section.gradeLevel', 'student.guardians', 'stop')
                ->where('bus_route_id', $route->id)->get()
                ->sortBy(fn (StudentTransport $t) => [$t->stop?->sequence ?? 999, $t->student->first_name_ar])->values()
                ->map(fn (StudentTransport $t) => [
                    'id' => $t->id, 'student_id' => $t->student_id, 'name' => $t->student->name,
                    'class' => $t->student->currentEnrollment?->section ? $t->student->currentEnrollment->section->gradeLevel->name.' / '.$t->student->currentEnrollment->section->name : null,
                    'stop_id' => $t->route_stop_id, 'stop' => $t->stop?->name,
                    'guardian_phone' => $t->student->guardians->sortByDesc(fn ($g) => $g->pivot->is_primary)->first()?->phone,
                ]),
            'buses' => Bus::query()->orderBy('number')->get(['id', 'number']),
            'results' => $search === '' ? [] : Student::query()->search($search)->with('transport.route')->limit(10)->get()
                ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->name, 'number' => $s->student_number, 'current' => $s->transport?->route?->name]),
            'search' => $search,
        ]);
    }

    public function saveStop(Request $request, BusRoute $route, ?RouteStop $stop = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sequence' => ['required', 'integer', 'min:0', 'max:999'],
            'pickup_at' => ['nullable', 'date_format:H:i'],
            'dropoff_at' => ['nullable', 'date_format:H:i'],
        ]);
        abort_if($stop && $stop->bus_route_id !== $route->id, 404);
        $stop ? $stop->update($data) : $route->stops()->create($data);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyStop(BusRoute $route, RouteStop $stop): RedirectResponse
    {
        abort_if($stop->bus_route_id !== $route->id, 404);
        $stop->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** Puts a student on this route (moving them from any other), at a stop. */
    public function assign(Request $request, BusRoute $route): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', ExistsInCurrentSchool::in('students')],
            'route_stop_id' => ['nullable', 'integer', Rule::exists('route_stops', 'id')->where('bus_route_id', $route->id)],
        ]);

        DB::transaction(function () use ($route, $data) {
            $route->load('bus');
            $existing = StudentTransport::query()->where('student_id', $data['student_id'])->first();
            $moving = $existing === null || $existing->bus_route_id !== $route->id;
            if ($moving && $route->bus && $route->riders()->count() >= $route->bus->capacity) {
                throw ValidationException::withMessages(['student_id' => __('transport.bus_full')]);
            }
            StudentTransport::query()->updateOrCreate(['student_id' => $data['student_id']], [
                'bus_route_id' => $route->id, 'route_stop_id' => $data['route_stop_id'] ?? null,
            ]);
        });

        return back()->with('success', __('Changes saved.'));
    }

    public function unassign(BusRoute $route, StudentTransport $rider): RedirectResponse
    {
        abort_if($rider->bus_route_id !== $route->id, 404);
        $rider->delete();

        return back()->with('success', __('Deleted.'));
    }
}
