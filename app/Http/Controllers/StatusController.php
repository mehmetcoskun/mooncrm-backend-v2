<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['status_Access', 'customer_Access', 'segment_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $user = auth()->user();
        $userRoles = $user->roles;
        $allowedStatusIds = [];

        foreach ($userRoles as $role) {
            $roleStatuses = $role->statuses;
            if ($roleStatuses->isEmpty()) {
                $allowedStatusIds = null;
                break;
            }
            $allowedStatusIds = array_merge($allowedStatusIds, $roleStatuses->pluck('id')->toArray());
        }

        if ($allowedStatusIds !== null) {
            $allowedStatusIds = array_unique($allowedStatusIds);
        }

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $statuses = Status::where(function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId)
                ->orWhere(function ($query) {
                    $query->whereNull('organization_id')
                        ->where('is_global', 1);
                });
        });

        if ($allowedStatusIds !== null) {
            $statuses->whereIn('id', $allowedStatusIds);
        }

        return $statuses->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['status_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        return Status::create($data)->load('organization');
    }

    public function update(Request $request, Status $status)
    {
        if (Gate::none(['status_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        $status->update($data);
        $status->load('organization');

        return response()->json($status);
    }

    public function destroy(Status $status)
    {
        if (Gate::none(['status_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $status->delete();
    }
}
