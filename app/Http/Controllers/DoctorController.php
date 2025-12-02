<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['doctor_Access', 'customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $doctors = Doctor::where('organization_id', $organizationId);

        return $doctors->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['doctor_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return Doctor::create($data)->load('organization');
    }

    public function update(Request $request, Doctor $doctor)
    {
        if (Gate::none(['doctor_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $doctor->update($data);
        $doctor->load('organization');

        return response()->json($doctor);
    }

    public function destroy(Doctor $doctor)
    {
        if (Gate::none(['doctor_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $doctor->delete();
    }
}