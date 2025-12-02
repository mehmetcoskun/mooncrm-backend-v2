<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SmsTemplateController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['sms_template_Access', 'marketing_BulkSms', 'marketing_SendSms']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        $smsTemplates = SmsTemplate::where('organization_id', $organizationId);

        if (in_array(3, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $smsTemplates->whereIn('language', $user->languages);
        }

        return $smsTemplates->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['sms_template_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return SmsTemplate::create($data)->load('organization');
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        if (Gate::none(['sms_template_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $smsTemplate->update($data);
        $smsTemplate->load('organization');

        return response()->json($smsTemplate);
    }

    public function destroy(SmsTemplate $smsTemplate)
    {
        if (Gate::none(['sms_template_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $smsTemplate->delete();
    }
}
