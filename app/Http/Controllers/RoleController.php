<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['role_Access', 'user_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ($organizationId) {
            $roles = Role::where('organization_id', $organizationId)
                ->orWhere(function ($query) {
                    $query->whereNull('organization_id')
                        ->where('is_global', 1);
                });
        } else {
            $roles = Role::where('organization_id', null);
        }

        if (!(auth()->user()->roles->contains('id', 1) || auth()->id() === 1)) {
            $roles->where('id', '!=', 1);
        }

        return $roles->orderBy('id', 'desc')->get()->load('organization', 'permissions', 'statuses');
    }

    public function store(Request $request)
    {
        if (Gate::none(['role_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        } else {
            $data['organization_id'] = null;
        }

        if ($request->has('permissions') && !(auth()->user()->roles->contains('id', 1) || auth()->id() === 1)) {
            $userPermissionIds = $this->getUserPermissionIds();
            $requestedPermissionIds = collect($request->permissions)->pluck('id');

            $validPermissionIds = $requestedPermissionIds->filter(function ($id) use ($userPermissionIds) {
                return $userPermissionIds->contains($id);
            });

            $role = Role::create($data);
            $role->permissions()->attach($validPermissionIds);
        } else {
            $role = Role::create($data);

            if ($request->has('permissions')) {
                $permissionIds = collect($request->permissions)->pluck('id');
                $role->permissions()->attach($permissionIds);
            }
        }

        if ($request->has('statuses')) {
            $statusIds = collect($request->statuses)->pluck('id');
            $role->statuses()->attach($statusIds);
        }

        return $role->load('organization', 'permissions', 'statuses');
    }

    public function update(Request $request, Role $role)
    {
        if (Gate::none(['role_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        } else {
            $data['organization_id'] = null;
        }

        $role->update($data);

        if ($request->has('permissions') && !(auth()->user()->roles->contains('id', 1) || auth()->id() === 1)) {
            $userPermissionIds = $this->getUserPermissionIds();
            $requestedPermissionIds = collect($request->permissions)->pluck('id');

            $validPermissionIds = $requestedPermissionIds->filter(function ($id) use ($userPermissionIds) {
                return $userPermissionIds->contains($id);
            });

            $role->permissions()->sync($validPermissionIds);
        } else if ($request->has('permissions')) {
            $permissionIds = collect($request->permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        if ($request->has('statuses')) {
            $statusIds = collect($request->statuses)->pluck('id');
            $role->statuses()->sync($statusIds);
        }

        $role->load('organization', 'permissions', 'statuses');

        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        if (Gate::none(['role_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $role->delete();
    }

    private function getUserPermissionIds()
    {
        $userRoles = auth()->user()->roles;
        $permissionIds = collect();

        foreach ($userRoles as $role) {
            $rolePermissions = $role->permissions->pluck('id');
            $permissionIds = $permissionIds->merge($rolePermissions);
        }

        return $permissionIds->unique();
    }
}
