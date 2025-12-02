<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['email_template_Access', 'marketing_BulkMail', 'marketing_SendMail']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        $emailTemplates = EmailTemplate::where('organization_id', $organizationId);

        if (in_array(3, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $emailTemplates->whereIn('language', $user->languages);
        }

        return $emailTemplates->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['email_template_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return EmailTemplate::create($data)->load('organization');
    }

    public function show(EmailTemplate $emailTemplate)
    {
        return $emailTemplate->load('organization');
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        if (Gate::none(['email_template_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $emailTemplate->update($data);
        $emailTemplate->load('organization');

        return response()->json($emailTemplate);
    }

    public function destroy(EmailTemplate $emailTemplate)
    {
        if (Gate::none(['email_template_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $emailTemplate->delete();
    }
}
