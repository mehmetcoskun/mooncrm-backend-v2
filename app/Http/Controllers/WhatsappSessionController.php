<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class WhatsappSessionController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['whatsapp_session_Access', 'marketing_BulkWhatsapp', 'marketing_SendWhatsapp']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $whatsappSessions = WhatsappSession::where('organization_id', $organizationId);

        return $whatsappSessions->orderBy('id', 'desc')->get()->load('organization');
    }

    public function store(Request $request)
    {
        if (Gate::none(['whatsapp_session_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::where('organization_id', $data['organization_id'])->first();

        if ($setting && isset($setting->whatsapp_settings['session_limit'])) {
            $sessionLimit = $setting->whatsapp_settings['session_limit'];
        }

        $currentSessionCount = WhatsappSession::where('organization_id', $data['organization_id'])->count();

        if ($currentSessionCount >= $sessionLimit) {
            return response()->json([
                'message' => "Maksimum {$sessionLimit} WhatsApp oturumu açabilirsiniz. Mevcut oturum sayısı: {$currentSessionCount}"
            ], 422);
        }

        if (!empty($data['phone'])) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();

                $cleanPhone = $data['phone'];
                $cleanPhone = preg_replace('/\s+/', '', $cleanPhone);
                $cleanPhone = preg_replace('/[^\d\+\(\)]/', '', $cleanPhone);
                $cleanPhone = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $cleanPhone);

                $data['phone'] = $cleanPhone;

                if (!str_starts_with($data['phone'], '+') && preg_match('/^\d+$/', $data['phone']) && strlen($data['phone']) > 10) {
                    $data['phone'] = '+' . $data['phone'];
                }

                $phoneNumber = $phoneUtil->parse($data['phone']);

                if (!$phoneUtil->isValidNumber($phoneNumber)) {
                    return response()->json(['message' => 'Geçersiz telefon numarası formatı.'], 422);
                }

                $data['phone'] = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);
                $data['phone'] = ltrim($data['phone'], '+');

            } catch (NumberParseException $e) {
                return response()->json(['message' => 'Geçersiz telefon numarası. Lütfen alan kodu ile birlikte giriniz. Örnek: 905555555555. Not: Numaranızda görünmeyen karakterler olabilir, kopyala-yapıştır yerine manuel giriş yapınız.'], 422);
            }
        } else {
            return response()->json(['message' => 'Telefon numarası zorunludur.'], 422);
        }

        $existingSession = WhatsappSession::where(function ($query) use ($data) {
                $query->where('title', $data['title'])
                    ->orWhere('phone', $data['phone']);
            })->first();

        if ($existingSession) {
            $errorMessage = '';
            if ($existingSession->title === $data['title']) {
                $errorMessage = 'Bu başlık zaten kullanılıyor.';
            } else if ($existingSession->phone === $data['phone']) {
                $errorMessage = 'Bu telefon numarası zaten kullanılıyor.';
            } else {
                $errorMessage = 'Bu başlık veya telefon numarası ile zaten bir oturum mevcut.';
            }

            return response()->json(['message' => $errorMessage], 422);
        }

        if (isset($data['is_admin']) && $data['is_admin']) {
            WhatsappSession::where('organization_id', $data['organization_id'])
                ->where('is_admin', true)
                ->update(['is_admin' => false]);
        }

        return WhatsappSession::create($data)->load('organization');
    }

    public function destroy(WhatsappSession $whatsappSession)
    {
        if (Gate::none(['whatsapp_session_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $whatsappSession->delete();
    }
}
