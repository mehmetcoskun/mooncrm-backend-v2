<?php

namespace App\Observers;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SettingObserver
{
    public function updated(Setting $setting): void
    {
        if (!$setting->wasChanged('facebook_settings')) {
            return;
        }

        $facebookSettings = $setting->facebook_settings ?? [];
        $originalFacebookSettings = $setting->getOriginal('facebook_settings') ?? [];

        $currentAccessToken = $facebookSettings['access_token'] ?? null;
        $originalAccessToken = $originalFacebookSettings['access_token'] ?? null;

        if (!$currentAccessToken || $currentAccessToken === $originalAccessToken) {
            return;
        }

        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');
        $graphVersion = config('services.facebook.graph_version');

        if (!$appId || !$appSecret) {
            return;
        }

        try {
            $response = Http::get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $currentAccessToken,
            ]);

            if (!$response->successful()) {
                return;
            }

            $data = $response->json();

            if (!is_array($data) || empty($data['access_token'])) {
                return;
            }

            $newFacebookSettings = array_merge($facebookSettings, [
                'access_token' => $data['access_token'],
                'expires_in' => $data['expires_in'] ?? $facebookSettings['expires_in'] ?? null,
                'login_time' => Carbon::now()->toIso8601String(),
            ]);

            $setting->facebook_settings = $newFacebookSettings;
            $setting->saveQuietly();
        } catch (\Throwable $exception) {
        }
    }
}

