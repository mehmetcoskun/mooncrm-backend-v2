<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['service_Access', 'customer_Access', 'statistic_Access', 'report_Access', 'segment_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $services = Service::where('organization_id', $organizationId);

        return $services->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['service_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return Service::create($data)->load('organization');
    }

    public function update(Request $request, Service $service)
    {
        if (Gate::none(['service_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $service->update($data);
        $service->load('organization');

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        if (Gate::none(['service_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $service->delete();
    }
}
