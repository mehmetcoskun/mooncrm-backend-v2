<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\WhatsappSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckCustomerReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-customer-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hatırlatıcı zamanı gelen müşteriler için danışmanlara WhatsApp mesajı gönderir.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customers = Customer::with(['user.whatsappSession', 'organization'])
            ->whereNotNull('reminder')
            ->whereJsonLength('reminder', '>', 0)
            ->get()
            ->filter(function ($customer) {
                $reminder = $customer->reminder;
                return $reminder['status'] && Carbon::parse($reminder['date'])->lte(Carbon::now());
            });

        foreach ($customers as $customer) {
            if (!$customer->user) {
                continue;
            }

            $settings = Setting::where('organization_id', $customer->organization_id)->first();
            if (!$settings || empty($settings->whatsapp_settings)) {
                continue;
            }

            $whatsappSettings = $settings->whatsapp_settings;
            $reminder = $customer->reminder;

            $adminSession = WhatsappSession::where('organization_id', $customer->organization_id)
                ->where('is_admin', true)
                ->first();

            if (!$adminSession) {
                $this->error("Admin WhatsApp oturumu bulunamadı - Firma ID: {$customer->organization_id}");
                continue;
            }

            if (!$customer->user->whatsappSession || !$customer->user->whatsappSession->phone) {
                $this->error("Danışman telefon numarası bulunamadı - Danışman: {$customer->user->name}");
                continue;
            }

            try {
                $message = "🔔 *Müşteri Hatırlatıcı Bildirimi*\n\n";
                $message .= "Merhaba {$customer->user->name},\n\n";
                $message .= "*{$customer->name}* isimli müşteri için bir hatırlatıcınız bulunmaktadır.\n\n";
                $message .= "*Telefon Numarası:* {$customer->phone}\n";
                $message .= "*Hatırlatıcı Notu:* {$reminder['notes']}\n";
                $message .= "*Hatırlatıcı Tarihi:* " . Carbon::parse($reminder['date'])->format('d.m.Y H:i');

                Http::withHeaders([
                    'X-Api-Key' => $settings->whatsapp_settings['api_key']
                ])->post($whatsappSettings['api_url'] . '/sendText', [
                    'chatId' => $customer->user->whatsappSession->phone . '@c.us',
                    'text' => $message,
                    'session' => $adminSession->title
                ]);

                $reminder['status'] = false;
                $customer->update([
                    'reminder' => $reminder
                ]);

                $this->info("Hatırlatıcı WhatsApp mesajı gönderildi: {$customer->user->name}");
            } catch (\Exception $e) {
                $this->error("WhatsApp mesajı gönderilemedi ({$customer->user->name}): " . $e->getMessage());
            }
        }
    }
}
