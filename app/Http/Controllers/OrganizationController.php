<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function index()
    {
        if (Gate::none(['organization_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return Organization::orderBy('id', 'desc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['organization_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->only(['name', 'code', 'trial_ends_at', 'license_ends_at', 'is_active']);

        if (!empty($data['trial_ends_at']) && !empty($data['license_ends_at'])) {
            return response()->json(['message' => 'Deneme süresi ve lisans süresi aynı anda tanımlanamaz. Lütfen sadece birini seçin.'], 400);
        }

        return Organization::create($data);
    }

    public function show(Organization $organization)
    {
        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization)
    {
        if (Gate::none(['organization_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->only(['name', 'code', 'trial_ends_at', 'license_ends_at', 'is_active']);

        if (!empty($data['trial_ends_at']) && !empty($data['license_ends_at'])) {
            return response()->json(['message' => 'Deneme süresi ve lisans süresi aynı anda tanımlanamaz. Lütfen sadece birini seçin.'], 400);
        }

        $organization->update($data);

        return response()->json($organization);
    }

    public function destroy(Organization $organization)
    {
        if (Gate::none(['organization_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $organization->delete();
    }
}
