<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Finds an existing guardian by national ID or mobile, so siblings are linked on admission. */
class GuardianLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:9', 'max:20']])['q']);
        $phone = PhoneNumber::normalize($q);

        $guardians = Guardian::query()
            ->with('students:id,first_name_ar,family_name_ar')
            ->where(fn ($query) => $query->where('national_id', $q)->when($phone, fn ($q2) => $q2->orWhere('phone', $phone)))
            ->limit(5)
            ->get();

        return response()->json(['data' => $guardians->map(fn (Guardian $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'phone' => $g->phone,
            'children' => $g->students->map(fn ($s) => $s->first_name_ar.' '.$s->family_name_ar)->values(),
        ])]);
    }
}
