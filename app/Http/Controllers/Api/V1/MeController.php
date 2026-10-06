<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class MeController extends Controller
{
    /** The signed-in user and the schools they can enter, with their roles in each. */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $user->memberships()->with('school')->where('status', 'active')->get();

        $rolesBySchool = Role::query()
            ->join(config('permission.table_names.model_has_roles').' as mhr', 'mhr.role_id', '=', 'roles.id')
            ->where('mhr.model_type', $user->getMorphClass())
            ->where('mhr.model_id', $user->getKey())
            ->get(['roles.name', 'mhr.school_id'])
            ->groupBy('school_id');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'is_platform_admin' => $user->is_platform_admin,
            'schools' => $memberships->map(fn (Membership $membership) => [
                'id' => $membership->school->id,
                'slug' => $membership->school->slug,
                'name' => $membership->school->name,
                'roles' => $rolesBySchool->get($membership->school_id, collect())->pluck('name')->values(),
            ])->values(),
        ]]);
    }
}
