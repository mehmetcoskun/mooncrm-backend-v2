<?php

namespace App\Http\Controllers;

use App\Models\PartnerTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PartnerTransferController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['partner_transfer_Access', 'customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $partnerTransfers = PartnerTransfer::where('organization_id', $organizationId);

        return $partnerTransfers->orderBy('name', 'asc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['partner_transfer_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return PartnerTransfer::create($data);
    }

    public function update(Request $request, PartnerTransfer $partnerTransfer)
    {
        if (Gate::none(['partner_transfer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $partnerTransfer->update($request->all());

        return response()->json($partnerTransfer);
    }

    public function destroy(PartnerTransfer $partnerTransfer)
    {
        if (Gate::none(['partner_transfer_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $partnerTransfer->delete();
    }
}
