<?php

namespace App\Http\Controllers\Web;

use App\Actions\Students\ChangeEnrollment;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function update(Request $request, Enrollment $enrollment, ChangeEnrollment $change): RedirectResponse
    {
        $change->handle($enrollment, $request->validate(ChangeEnrollment::rules()));

        return back()->with('success', __('Changes saved.'));
    }
}
