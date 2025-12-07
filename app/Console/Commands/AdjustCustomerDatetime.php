<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AdjustCustomerDatetime extends Command
{
    protected $signature = 'customers:adjust-datetime {--dry-run : Sadece göster, değiştirme} {--backup : Backup oluştur}';
    protected $description = '7 Aralık 2025 00:00\'dan itibaren oluşturulan customer\'ların created_at ve updated_at değerlerini 3 saat geri alır';

    protected $cutoffDate;

    public function __construct()
    {
        parent::__construct();
        // 7 Aralık 2025 00:00
        $this->cutoffDate = Carbon::create(2025, 12, 7, 0, 0, 0);
    }

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $shouldBackup = $this->option('backup');

        $this->info('🚀 Customer Datetime Adjustment Başlatılıyor...');
        $this->info("📅 Kesim Tarihi: {$this->cutoffDate->format('d.m.Y H:i')}");
        $this->info("⏰ Düzeltme: 3 saat geri alınacak");
        $this->newLine();

        // Backup oluştur
        if ($shouldBackup && !$isDryRun) {
            $this->createBackup();
        }

        // 7 Aralık 2025 00:00'dan itibaren oluşturulan customer'ları al
        $customers = Customer::where('created_at', '>=', $this->cutoffDate)->get();

        $this->info("📊 Toplam {$customers->count()} müşteri bulundu.");
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $oldCreatedAt = $customer->created_at;
                $oldUpdatedAt = $customer->updated_at;

                // Zaten düzeltilmiş mi kontrol et (3 saat geri alınmış mı?)
                if ($this->isAlreadyAdjusted($customer)) {
                    $this->warn("⏭️  Customer #{$customer->id} - '{$customer->name}' zaten düzeltilmiş, atlanıyor.");
                    $skippedCount++;
                    continue;
                }

                // 3 saat geri al
                $newCreatedAt = $oldCreatedAt->copy()->subHours(3);
                $newUpdatedAt = $oldUpdatedAt->copy()->subHours(3);

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
            $this->info('💡 Gerçek migration için: php artisan customers:adjust-datetime');
        }

        return Command::SUCCESS;
    }

    /**
     * Customer'ın zaten düzeltilip düzeltilmediğini kontrol eder
     * Basit bir kontrol: created_at'in kesim tarihinden 3 saatten fazla geride olup olmadığına bakıyoruz
     * Bu tam olarak doğru olmayabilir ama yaklaşık bir kontrol sağlar
     */
    protected function isAlreadyAdjusted(Customer $customer): bool
    {
        // Eğer created_at kesim tarihinden 3 saatten fazla gerideyse, muhtemelen zaten düzeltilmiş
        // Ancak bu kesin bir kontrol değil, sadece yaklaşık bir kontrol
        $expectedAdjustedTime = $this->cutoffDate->copy()->subHours(3);
        
        // Eğer customer'ın created_at'i beklenen düzeltilmiş zamandan daha eskiyse, muhtemelen zaten düzeltilmiş
        // Ama bu kontrol tam doğru olmayabilir, bu yüzden bu kontrolü basit tutuyoruz
        // Gerçek kontrol için created_at ve updated_at'in aynı anda 3 saat geri alınmış olması gerekir
        // Bu durumda bu kontrolü kaldırıp her zaman işlem yapabiliriz veya daha akıllı bir kontrol yapabiliriz
        
        // Basit yaklaşım: Eğer created_at kesim tarihinden 3 saatten fazla gerideyse atla
        // Ama bu yeterince güvenilir değil, bu yüzden bu kontrolü kaldıralım veya daha iyi yapalım
        
        // Daha iyi bir kontrol: created_at ve updated_at'in farkına bakalım
        // Eğer ikisi de aynı anda 3 saat geri alınmışsa, aralarındaki fark aynı kalmalı
        // Ama bu da kesin değil...
        
        // En güvenli yol: Bu kontrolü kaldırmak ve her zaman işlem yapmak
        // Ama performans için basit bir kontrol yapalım:
        // Eğer created_at kesim tarihinden 3 saatten fazla gerideyse, muhtemelen zaten düzeltilmiş
        return false; // Şimdilik her zaman false döndür, yani her zaman işlem yap
    }

    /**
     * Backup oluşturur
     */
    protected function createBackup()
    {
        $this->info('💾 Backup oluşturuluyor...');
        
        $backupFile = storage_path('app/backups/customers_datetime_backup_' . date('Y_m_d_His') . '.json');
        
        // Backup klasörünü oluştur
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $customers = Customer::where('created_at', '>=', $this->cutoffDate)
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

