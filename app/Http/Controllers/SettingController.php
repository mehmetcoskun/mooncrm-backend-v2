<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['setting_Access', 'marketing_BulkMail', 'marketing_BulkSms', 'marketing_BulkWhatsapp', 'marketing_SendMail', 'marketing_SendSms', 'marketing_SendWhatsapp']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::where('organization_id', $organizationId)->first();

        return response()->json($setting);
    }

    public function updateOrCreate(Request $request)
    {
        if (Gate::none(['setting_Mail', 'setting_Sms', 'setting_Whatsapp', 'setting_DailyReport', 'setting_SalesMail', 'setting_LeadAssignment', 'setting_WelcomeMessage']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::updateOrCreate(
            ['organization_id' => $organizationId],
            $request->all()
        );

        return response()->json($setting);
    }

    public function verifyMail(Request $request)
    {
        $transport = new EsmtpTransport($request->smtp_host, $request->smtp_port);
        $transport->setUsername($request->smtp_username);
        $transport->setPassword($request->smtp_password);

        try {
            $transport->start();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Mail bilgileri doğrulanamadı. Lütfen bilgileri kontrol edin.'], 400);
        }

        return response()->json(['message' => 'Mail bilgileri doğrulandı.']);
    }
}
