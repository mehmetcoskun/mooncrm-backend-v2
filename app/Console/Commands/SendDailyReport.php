<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Günlük rapor istatistiklerini WhatsApp üzerinden gönderir.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settings = Setting::all();

        foreach ($settings as $setting) {
            if (empty($setting->daily_report_settings)) {
                continue;
            }

            $reportSettings = $setting->daily_report_settings;
            $whatsappSettings = $setting->whatsapp_settings;

            if (empty($reportSettings['chat_id']) || empty($reportSettings['message_template']) || empty($whatsappSettings['api_url'])) {
                continue;
            }

            $adminSession = WhatsappSession::where('organization_id', $setting->organization_id)
                ->where('is_admin', true)
                ->first();

            if (!$adminSession) {
                $this->error("Admin WhatsApp oturumu bulunamadı - Firma ID: {$setting->organization_id}");
                continue;
            }

            $users = User::where('organization_id', $setting->organization_id)
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->where('roles.id', 3);
                })
                ->get();

            foreach ($users as $user) {
                $today = Carbon::today();

                $dailyLeads = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->whereDate('created_at', $today)
                    ->count();

                $totalNoPhotos = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where('status_id', 3)
                    ->count();

                $totalWaitingOffer = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where('status_id', 4)
                    ->count();

                $dailySales = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where(function ($query) use ($today) {
                        $query->whereJsonContains('sales_info', ['sales_date' => $today->format('Y-m-d')])
                            ->orWhere(function ($q) use ($today) {
                                $q->whereRaw("JSON_SEARCH(sales_info, 'one', ?, null, '$[*].sales_date') IS NOT NULL", [$today->format('Y-m-d')]);
                            });
                    })
                    ->count();

                $dailyOffered = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where('status_id', 5)
                    ->whereDate('updated_at', $today)
                    ->count();

                $dailyCalledPatients = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where(function ($query) use ($today) {
                        $query->whereJsonContains('phone_calls', ['date' => $today->format('Y-m-d')])
                            ->orWhere(function ($q) use ($today) {
                                $q->whereRaw("JSON_SEARCH(phone_calls, 'one', ?, null, '$[*].date') IS NOT NULL", [$today->format('Y-m-d')]);
                            });
                    })
                    ->count();

                $totalPositive = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where('status_id', 6)
                    ->count();

                $totalWaitingTicket = Customer::where('organization_id', $setting->organization_id)
                    ->where('user_id', $user->id)
                    ->where('status_id', 7)
                    ->count();

                $message = $reportSettings['message_template'];
                $replacements = [
                    '{user}' => $user->name,
                    '{date}' => $today->format('d.m.Y'),
                    '{daily_leads}' => $dailyLeads,
                    '{total_no_photos}' => $totalNoPhotos,
                    '{total_waiting_offer}' => $totalWaitingOffer,
                    '{daily_sales}' => $dailySales,
                    '{daily_offered}' => $dailyOffered,
                    '{daily_called_patients}' => $dailyCalledPatients,
                    '{total_positive}' => $totalPositive,
                    '{total_waiting_ticket}' => $totalWaitingTicket,
                ];

                $message = str_replace(array_keys($replacements), array_values($replacements), $message);

                try {
                    Http::withHeaders([
                        'X-Api-Key' => $whatsappSettings['api_key']
                    ])->post($whatsappSettings['api_url'] . '/sendText', [
                        'chatId' => $reportSettings['chat_id'],
                        'text' => $message,
                        'session' => $adminSession->title
                    ]);

                    $this->info("Günlük rapor gönderildi: {$user->name}");
                } catch (\Exception $e) {
                    $this->error("Rapor gönderilemedi ({$user->name}): " . $e->getMessage());
                }
            }
        }
    }
}
