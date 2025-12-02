<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['permission_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ($organizationId) {
            $permissions = Permission::where('organization_id', $organizationId)
                ->orWhere(function ($query) {
                    $query->whereNull('organization_id')
                        ->where('is_global', 1);
                });
        } else {
            $permissions = Permission::where('organization_id', null);
        }

        return $permissions->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['permission_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        return Permission::create($data)->load('organization');
    }

    public function update(Request $request, Permission $permission)
    {
        if (Gate::none(['permission_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        $permission->update($data);
        $permission->load('organization');

        return response()->json($permission);
    }

    public function destroy(Permission $permission)
    {
        if (Gate::none(['permission_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $permission->delete();
    }

    public function getAvailablePermissions()
    {
        if (auth()->user()->roles->contains('id', 1) && auth()->id() === 1) {
            return Permission::all();
        }

        $userRoles = auth()->user()->roles;

        $permissionIds = collect();
        foreach ($userRoles as $role) {
            $rolePermissions = $role->permissions->pluck('id');
            $permissionIds = $permissionIds->merge($rolePermissions);
        }

        return Permission::whereIn('id', $permissionIds->unique())->get();
    }
}
