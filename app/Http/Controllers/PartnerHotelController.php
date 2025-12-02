<?php

namespace App\Http\Controllers;

use App\Models\PartnerHotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PartnerHotelController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['partner_hotel_Access', 'customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $partnerHotels = PartnerHotel::where('organization_id', $organizationId);

        return $partnerHotels->orderBy('name', 'asc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['partner_hotel_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return PartnerHotel::create($data);
    }

    public function update(Request $request, PartnerHotel $partnerHotel)
    {
        if (Gate::none(['partner_hotel_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $partnerHotel->update($request->all());

        return response()->json($partnerHotel);
    }

    public function destroy(PartnerHotel $partnerHotel)
    {
        if (Gate::none(['partner_hotel_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $partnerHotel->delete();
    }
}
