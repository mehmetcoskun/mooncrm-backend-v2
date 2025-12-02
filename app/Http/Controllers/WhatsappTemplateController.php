<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WhatsappTemplateController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['whatsapp_template_Access', 'marketing_BulkWhatsapp', 'marketing_SendWhatsapp']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $whatsappTemplates = WhatsappTemplate::where('organization_id', $organizationId);

        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        if (in_array(3, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $whatsappTemplates->whereIn('language', $user->languages);
        }

        return $whatsappTemplates->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['whatsapp_template_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return WhatsappTemplate::create($data)->load('organization');
    }

    public function update(Request $request, WhatsappTemplate $whatsappTemplate)
    {
        if (Gate::none(['whatsapp_template_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $whatsappTemplate->update($data);
        $whatsappTemplate->load('organization');

        return response()->json($whatsappTemplate);
    }

    public function destroy(WhatsappTemplate $whatsappTemplate)
    {
        if (Gate::none(['whatsapp_template_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $whatsappTemplate->delete();
    }
}
