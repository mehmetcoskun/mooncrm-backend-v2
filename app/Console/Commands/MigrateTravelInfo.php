<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class MigrateTravelInfo extends Command
{
    protected $signature = 'customers:migrate-travel-info {--dry-run : Sadece göster, değiştirme} {--backup : Backup oluştur}';
    protected $description = 'Customer travel_info kolonundaki partner_hotel_id ve partner_transfer_id alanlarını hotel_id ve transfer_id olarak değiştirir, service alanını notes\'a taşır';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $shouldBackup = $this->option('backup');

        $this->info('🚀 Travel Info Migration Başlatılıyor...');
        $this->newLine();

        // Backup oluştur
        if ($shouldBackup && !$isDryRun) {
            $this->createBackup();
        }

        // travel_info'su olan tüm müşterileri al
        $customers = Customer::whereNotNull('travel_info')
            ->where('travel_info', '!=', '[]')
            ->where('travel_info', '!=', 'null')
            ->get();

        $this->info("📊 Toplam {$customers->count()} müşteri bulundu.");
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $travelInfo = $customer->travel_info;

                // travel_info boş veya array değilse atla
                if (empty($travelInfo) || !is_array($travelInfo)) {
                    $skippedCount++;
                    continue;
                }

                // Zaten yeni formatta mı kontrol et
                if ($this->isAlreadyMigrated($travelInfo)) {
                    $this->warn("⏭️  Customer #{$customer->id} - '{$customer->name}' zaten yeni formatta, atlanıyor.");
                    $skippedCount++;
                    continue;
                }

                // Eski formattan yeni formata dönüştür
                $newTravelInfo = $this->convertTravelInfo($travelInfo);

                if ($isDryRun) {
                    $this->info("🔍 Customer #{$customer->id} - '{$customer->name}'");
                    $this->line("   Eski: " . json_encode($travelInfo, JSON_UNESCAPED_UNICODE));
                    $this->line("   Yeni: " . json_encode($newTravelInfo, JSON_UNESCAPED_UNICODE));
                    $this->newLine();
                } else {
                    // Güncelle
                    $customer->travel_info = $newTravelInfo;
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
            $this->info('💡 Gerçek migration için: php artisan customers:migrate-travel-info');
        }

        return Command::SUCCESS;
    }

    /**
     * Travel info'nun zaten migrate edilip edilmediğini kontrol eder
     */
    protected function isAlreadyMigrated(array $travelInfo): bool
    {
        foreach ($travelInfo as $travel) {
            // Eski alan adları varsa, henüz migrate edilmemiş
            if (isset($travel['partner_hotel_id']) || isset($travel['partner_transfer_id']) || isset($travel['service'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Travel info'yu yeni formata dönüştürür
     */
    protected function convertTravelInfo(array $travelInfo): array
    {
        $newTravelInfo = [];

        foreach ($travelInfo as $travel) {
            $newTravel = [];
            $oldService = null;

            foreach ($travel as $key => $value) {
                // partner_hotel_id -> hotel_id
                if ($key === 'partner_hotel_id') {
                    $newTravel['hotel_id'] = $value;
                }
                // partner_transfer_id -> transfer_id
                elseif ($key === 'partner_transfer_id') {
                    $newTravel['transfer_id'] = $value;
                }
                // service -> notes'a eklenecek
                elseif ($key === 'service') {
                    $oldService = $value;
                }
                // Diğer alanları olduğu gibi koru
                else {
                    $newTravel[$key] = $value;
                }
            }

            // Eski service değerini notes'a ekle
            if (!empty($oldService)) {
                $currentNotes = $newTravel['notes'] ?? '';
                
                if (!empty($currentNotes)) {
                    $newTravel['notes'] = $currentNotes . "\n\nEski Hizmetler:\n" . $oldService;
                } else {
                    $newTravel['notes'] = "Eski Hizmetler:\n" . $oldService;
                }
            }

            $newTravelInfo[] = $newTravel;
        }

        return $newTravelInfo;
    }

    /**
     * Backup oluşturur
     */
    protected function createBackup()
    {
        $this->info('💾 Backup oluşturuluyor...');
        
        $backupFile = storage_path('app/backups/customers_travel_info_backup_' . date('Y_m_d_His') . '.json');
        
        // Backup klasörünü oluştur
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $customers = Customer::whereNotNull('travel_info')
            ->where('travel_info', '!=', '[]')
            ->where('travel_info', '!=', 'null')
            ->get(['id', 'name', 'travel_info'])
            ->toArray();
            
        file_put_contents($backupFile, json_encode($customers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ Backup oluşturuldu: {$backupFile}");
        $this->newLine();
    }
}

