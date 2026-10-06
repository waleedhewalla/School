<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSwitchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Schools/Select');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['school_id' => ['required', 'integer']]);
        $school = School::query()->find($data['school_id']);

        abort_unless($school !== null && $request->user()->canEnterSchool($school), 403, __('tenancy.not_a_member'));

        $request->session()->put('school_id', $school->id);

        return redirect()->route('dashboard');
    }
}
