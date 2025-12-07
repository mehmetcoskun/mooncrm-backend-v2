<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AddHoursToSpecificCustomers extends Command
{
    protected $signature = 'customers:add-hours-specific {--dry-run : Sadece göster, değiştirme} {--backup : Backup oluştur}';
    protected $description = 'Belirli customer ID\'lerinin created_at ve updated_at değerlerini 3 saat ileri alır';

    // Terminal seçiminden alınan customer ID'leri
    protected $customerIds = [
        31212, 35609, 41138, 50889, 52063, 52133, 52346, 52347, 52348, 52350,
        52351, 52352, 52353, 52354, 52355, 52356, 52357, 52358, 52359, 52360,
        52361, 52362, 52363, 52364, 52365, 52366, 52368, 52369, 52370, 52371,
        52372, 52373, 52374, 52375, 52376, 52377, 52378, 52379, 52380, 52381,
        52382, 52383, 52384, 52385,
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $shouldBackup = $this->option('backup');

        $this->info('🚀 Specific Customer Datetime Adjustment Başlatılıyor...');
        $this->info("📋 İşlenecek Customer ID Sayısı: " . count($this->customerIds));
        $this->info("⏰ Düzeltme: 3 saat ileri alınacak (+3 saat)");
        $this->newLine();

        // Backup oluştur
        if ($shouldBackup && !$isDryRun) {
            $this->createBackup();
        }

        // Belirtilen ID'lere sahip customer'ları al
        $customers = Customer::whereIn('id', $this->customerIds)->get();

        $this->info("📊 Toplam {$customers->count()} müşteri bulundu.");
        
        // Bulunamayan ID'leri göster
        $foundIds = $customers->pluck('id')->toArray();
        $missingIds = array_diff($this->customerIds, $foundIds);
        if (!empty($missingIds)) {
            $this->warn("⚠️  Bulunamayan Customer ID'leri: " . implode(', ', $missingIds));
        }
        
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $oldCreatedAt = $customer->created_at;
                $oldUpdatedAt = $customer->updated_at;

                // 3 saat ileri al
                $newCreatedAt = $oldCreatedAt->copy()->addHours(3);
                $newUpdatedAt = $oldUpdatedAt->copy()->addHours(3);

                if ($isDryRun) {
                    $this->info("🔍 Customer #{$customer->id} - '{$customer->name}'");
                    $this->line("   Eski created_at: {$oldCreatedAt->format('Y-m-d H:i:s')}");
                    $this->line("   Yeni created_at: {$newCreatedAt->format('Y-m-d H:i:s')}");
                    $this->line("   Eski updated_at: {$oldUpdatedAt->format('Y-m-d H:i:s')}");
                    $this->line("   Yeni updated_at: {$newUpdatedAt->format('Y-m-d H:i:s')}");
                    $this->newLine();
                } else {
                    // Güncelle
                    $customer->created_at = $newCreatedAt;
                    $customer->updated_at = $newUpdatedAt;
                    $customer->save();
                    $this->info("✅ Customer #{$customer->id} - '{$customer->name}' güncellendi.");
                }

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $errorMsg = "Customer #{$customer->id} - '{$customer->name}': {$e->getMessage()}";
                $errors[] = $errorMsg;
                $this->error("❌ " . $errorMsg);
            }
        }

        // Özet
        $this->newLine();
        $this->info('📈 Migration Özeti:');
        $this->table(
            ['Durum', 'Adet'],
            [
                ['Başarılı', $successCount],
                ['Atlanan', $skippedCount],
                ['Hatalı', $errorCount],
                ['Toplam', $customers->count()],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->error('⚠️  Hatalar:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('🔍 DRY-RUN modu aktif, hiçbir değişiklik yapılmadı.');
            $this->info('💡 Gerçek migration için: php artisan customers:add-hours-specific');
        }

        return Command::SUCCESS;
    }

    /**
     * Backup oluşturur
     */
    protected function createBackup()
    {
        $this->info('💾 Backup oluşturuluyor...');
        
        $backupFile = storage_path('app/backups/customers_specific_add_hours_backup_' . date('Y_m_d_His') . '.json');
        
        // Backup klasörünü oluştur
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $customers = Customer::whereIn('id', $this->customerIds)
            ->get(['id', 'name', 'created_at', 'updated_at'])
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'created_at' => $customer->created_at->toDateTimeString(),
                    'updated_at' => $customer->updated_at->toDateTimeString(),
                ];
            })
            ->toArray();
            
        file_put_contents($backupFile, json_encode($customers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ Backup oluşturuldu: {$backupFile}");
        $this->newLine();
    }
}

