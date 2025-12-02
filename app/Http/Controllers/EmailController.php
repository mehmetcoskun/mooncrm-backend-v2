<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;

class EmailController extends Controller
{
    public function send(Request $request)
    {
        if (Gate::none(['marketing_SendMail']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = $request->header('X-Organization-Id');

        $settings = Setting::where('organization_id', $organizationId)->first();
        $smtpSettings = $settings->mail_settings;

        $transport = new EsmtpTransport($smtpSettings['smtp_host'], $smtpSettings['smtp_port']);
        $transport->setUsername($smtpSettings['smtp_username']);
        $transport->setPassword($smtpSettings['smtp_password']);

        try {
            $transport->start();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Mail bilgileri doğrulanamadı. Lütfen bilgileri kontrol edin.'], 400);
        }

        $mailer = new Mailer($transport);

        $email = (new Email())
            ->from(new Address($smtpSettings['smtp_username'], $smtpSettings['smtp_from_name']))
            ->subject($request->subject)
            ->to(new Address($request->customer['email'], $request->customer['name'] ?? ''))
            ->html($request->html);

        try {
            $mailer->send($email);

            return response()->json([
                'message' => 'E-posta başarıyla gönderildi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'E-posta gönderimi sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}
