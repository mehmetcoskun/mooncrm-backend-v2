<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['hotel_Access', 'customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $hotels = Hotel::where('organization_id', $organizationId);

        return $hotels->orderBy('name', 'asc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['hotel_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return Hotel::create($data);
    }

    public function update(Request $request, Hotel $hotel)
    {
        if (Gate::none(['hotel_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $hotel->update($request->all());

        return response()->json($hotel);
    }

    public function destroy(Hotel $hotel)
    {
        if (Gate::none(['hotel_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $hotel->delete();
    }
}
