<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['transfer_Access', 'customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $transfers = Transfer::where('organization_id', $organizationId);

        return $transfers->orderBy('name', 'asc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['transfer_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return Transfer::create($data);
    }

    public function update(Request $request, Transfer $transfer)
    {
        if (Gate::none(['transfer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $transfer->update($request->all());

        return response()->json($transfer);
    }

    public function destroy(Transfer $transfer)
    {
        if (Gate::none(['transfer_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $transfer->delete();
    }
}
