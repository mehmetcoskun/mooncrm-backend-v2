<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RefreshFacebookTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-facebook-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Meta access tokens before they expire';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Facebook token refresh process...');

        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');
        $graphVersion = config('services.facebook.graph_version');

        if (!$appId || !$appSecret) {
            $this->error('Facebook app credentials not configured!');
            return 1;
        }

        $settings = Setting::whereNotNull('facebook_settings')
            ->get();

        if ($settings->isEmpty()) {
            $this->info('No Facebook settings found.');
            return 0;
        }

        $refreshedCount = 0;
        $failedCount = 0;

        foreach ($settings as $setting) {
            $facebookSettings = $setting->facebook_settings ?? [];
            $accessToken = $facebookSettings['access_token'] ?? null;
            $expiresIn = $facebookSettings['expires_in'] ?? null;
            $loginTime = $facebookSettings['login_time'] ?? null;

            if (!$accessToken || !$expiresIn || !$loginTime) {
                $this->warn("Skipping setting ID {$setting->id} - missing required token data");
                continue;
            }

            $loginDate = Carbon::parse($loginTime);
            $expiryDate = $loginDate->addSeconds($expiresIn);
            $daysUntilExpiry = Carbon::now()->diffInDays($expiryDate, false);

            $this->info("Setting ID: {$setting->id}, Organization ID: {$setting->organization_id}");
            $this->info("Token expires in {$daysUntilExpiry} days ({$expiryDate->format('Y-m-d H:i:s')})");

            if ($daysUntilExpiry <= 7) {
                $this->info("Token expires soon, refreshing...");

                try {
                    $response = Http::get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                        'grant_type' => 'fb_exchange_token',
                        'client_id' => $appId,
                        'client_secret' => $appSecret,
                        'fb_exchange_token' => $accessToken,
                    ]);

                    if (!$response->successful()) {
                        $this->error("Failed to refresh token for organization {$setting->organization_id}: HTTP {$response->status()}");
                        $failedCount++;
                        continue;
                    }

                    $data = $response->json();

                    if (!is_array($data) || empty($data['access_token'])) {
                        $this->error("Invalid response format for organization {$setting->organization_id}");
                        $failedCount++;
                        continue;
                    }

                    $facebookSettings['access_token'] = $data['access_token'];

                    if (isset($data['expires_in'])) {
                        $facebookSettings['expires_in'] = $data['expires_in'];
                    }

                    $facebookSettings['login_time'] = Carbon::now()->toIso8601String();

                    $setting->updateQuietly(['facebook_settings' => $facebookSettings]);

                    $this->info("✓ Token refreshed successfully for organization {$setting->organization_id}");

                    $refreshedCount++;

                } catch (\Throwable $exception) {
                    $this->error("Exception while refreshing token for organization {$setting->organization_id}: {$exception->getMessage()}");
                    $failedCount++;
                }
            } else {
                $this->comment("Token is still valid, no refresh needed");
            }
        }

        $this->newLine();
        $this->info("Facebook token refresh process completed:");
        $this->info("- Total settings checked: " . $settings->count());
        $this->info("- Tokens refreshed: {$refreshedCount}");
        $this->info("- Failed refreshes: {$failedCount}");

        return 0;
    }
}
