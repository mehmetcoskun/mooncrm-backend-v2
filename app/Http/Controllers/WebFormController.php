<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WebForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class WebFormController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['web_form_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $webForms = WebForm::where('organization_id', $organizationId);

        return $webForms->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['web_form_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return WebForm::create($data)->load('organization');
    }

    public function show(WebForm $webForm)
    {
        return $webForm->load('organization');
    }

    public function update(Request $request, WebForm $webForm)
    {
        if (Gate::none(['web_form_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (isset($data['email_recipients'])) {
            $settings = Setting::where('organization_id', $webForm->organization_id)->first();
            if ($settings && $settings->mail_settings) {
                $smtpSettings = $settings->mail_settings;
                $recipients = array_filter(array_map('trim', explode(',', $data['email_recipients'])));
                $senderEmail = $smtpSettings['smtp_username'];

                if (in_array($senderEmail, $recipients)) {
                    return response()->json([
                        'message' => 'Gönderici e-posta adresi alıcılar listesinde bulunmamalıdır. Lütfen alıcı listesinden gönderici e-posta adresini çıkarın.'
                    ], 400);
                }
            }
        }

        $webForm->update($data);
        $webForm->load('organization');

        return response()->json($webForm);
    }

    public function destroy(WebForm $webForm)
    {
        if (Gate::none(['web_form_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $webForm->delete();
    }

    public function iframe(Request $request)
    {
        $webForm = WebForm::where('uuid', $request->uuid)->first();

        if (!$webForm)
            return response()->json(null, 404);

        return response()->json($webForm);
    }

    public function submit(Request $request)
    {
        $webForm = WebForm::where('uuid', $request->uuid)->first();
        if (!$webForm)
            return response()->json(null, 404);

        $rateLimitSettings = $webForm->rate_limit_settings ?? ['enabled' => true, 'maxSubmissionsPerMinute' => 1];

        if ($rateLimitSettings['enabled'] ?? true) {
            $clientIp = $request->ip();
            $maxSubmissions = $rateLimitSettings['maxSubmissionsPerMinute'] ?? 1;
            $rateKey = "webform_submit_{$clientIp}_{$webForm->uuid}";

            $currentCount = Cache::get($rateKey, 0);

            if ($currentCount >= $maxSubmissions) {
                return response()->json([
                    'error' => 'rate_limit_exceeded'
                ], 429);
            }

            Cache::put($rateKey, $currentCount + 1, 60);
        }

        $data = $request->all();
        $data['organization_id'] = $webForm->organization_id;

        $customerController = new CustomerController();
        $customerController->handleCustomerEntry($data);

        $settings = Setting::where('organization_id', $webForm->organization_id)->first();
        if (!$settings || !$settings->mail_settings || !$webForm->email_recipients) {
            return true;
        }

        $smtpSettings = $settings->mail_settings;
        $recipients = array_filter(array_map('trim', explode(',', $webForm->email_recipients)));

        if (empty($recipients)) {
            return true;
        }

        $transport = new EsmtpTransport($smtpSettings['smtp_host'], $smtpSettings['smtp_port']);
        $transport->setUsername($smtpSettings['smtp_username']);
        $transport->setPassword($smtpSettings['smtp_password']);

        $mailer = new Mailer($transport);

        $excludedKeys = ['organization_id', 'category_id'];
        $emailBody = '';
        foreach ($data as $key => $value) {
            if (in_array($key, $excludedKeys)) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $emailBody .= ucfirst($key) . ': ' . $value . "\n";
        }

        $email = (new Email())
            ->from(new Address($smtpSettings['smtp_username'], $smtpSettings['smtp_from_name']))
            ->subject($webForm->title . ' - Yeni Web Formu: ' . now()->format('d.m.Y H:i:s'))
            ->text($emailBody);

        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
