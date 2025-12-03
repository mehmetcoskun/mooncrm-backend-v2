<?php

namespace App\Console\Commands;

use App\Models\Segment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSegmentFilters extends Command
{
    protected $signature = 'segments:migrate-filters {--dry-run : Sadece göster, değiştirme} {--backup : Backup oluştur}';
    protected $description = 'Segment filtrelerini eski formattan yeni formata dönüştürür';

    // Alan eşleştirmeleri
    protected $fieldMapping = [
        'category_id' => 'categories',
        'service_ids' => 'services',
        'status_id' => 'statuses',
        'user_id' => 'users',
        'country' => 'countries',
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $shouldBackup = $this->option('backup');

        $this->info('🚀 Segment Filter Migration Başlatılıyor...');
        $this->newLine();

        // Backup oluştur
        if ($shouldBackup && !$isDryRun) {
            $this->createBackup();
        }

        // Tüm segmentleri al
        $segments = Segment::whereNotNull('filters')->get();
        $this->info("📊 Toplam {$segments->count()} segment bulundu.");
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($segments as $segment) {
            try {
                $filters = $segment->filters;

                // Zaten yeni formatta mı kontrol et
                if ($this->isNewFormat($filters)) {
                    $this->warn("⏭️  Segment #{$segment->id} - '{$segment->title}' zaten yeni formatta, atlanıyor.");
                    $skippedCount++;
                    continue;
                }

                // Eski formattan yeni formata dönüştür
                $newFilters = $this->convertFilters($filters);

                if ($isDryRun) {
                    $this->info("🔍 Segment #{$segment->id} - '{$segment->title}'");
                    $this->line("   Eski: " . json_encode($filters, JSON_UNESCAPED_UNICODE));
                    $this->line("   Yeni: " . json_encode($newFilters, JSON_UNESCAPED_UNICODE));
                    $this->newLine();
                } else {
                    // Güncelle
                    $segment->filters = $newFilters;
                    $segment->save();
                    $this->info("✅ Segment #{$segment->id} - '{$segment->title}' güncellendi.");
                }

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $errorMsg = "Segment #{$segment->id} - '{$segment->title}': {$e->getMessage()}";
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
                ['Toplam', $segments->count()],
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
            $this->info('💡 Gerçek migration için: php artisan segments:migrate-filters');
        }

        return Command::SUCCESS;
    }

    /**
     * Filtrelerin yeni formatta olup olmadığını kontrol eder
     */
    protected function isNewFormat(array $filters): bool
    {
        if (!isset($filters['conditions']) || !is_array($filters['conditions'])) {
            return false;
        }

        // İlk condition'ı kontrol et
        foreach ($filters['conditions'] as $condition) {
            $field = $condition['field'] ?? '';
            
            // Eğer eski alan adlarından biri varsa, eski format
            if (in_array($field, array_keys($this->fieldMapping))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Eski format filtreleri yeni formata dönüştürür
     */
    protected function convertFilters(array $filters): array
    {
        if (!isset($filters['conditions']) || !is_array($filters['conditions'])) {
            return $filters;
        }

        $newConditions = [];

        foreach ($filters['conditions'] as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '';
            $value = $condition['value'] ?? null;

            // Alan adını dönüştür
            $newField = $this->fieldMapping[$field] ?? $field;

            // Value'yu string array'e dönüştür (eğer array ise)
            $newValue = $value;
            if (is_array($value)) {
                $newValue = array_map(function($v) {
                    return (string) $v;
                }, $value);
            }

            $newConditions[] = [
                'field' => $newField,
                'operator' => $operator,
                'value' => $newValue,
            ];
        }

        return [
            'conditions' => $newConditions,
            'logicalOperator' => $filters['logicalOperator'] ?? 'and',
        ];
    }

    /**
     * Backup oluşturur
     */
    protected function createBackup()
    {
        $this->info('💾 Backup oluşturuluyor...');
        
        $backupFile = storage_path('app/backups/segments_backup_' . date('Y_m_d_His') . '.json');
        
        // Backup klasörünü oluştur
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $segments = Segment::whereNotNull('filters')->get()->toArray();
        file_put_contents($backupFile, json_encode($segments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ Backup oluşturuldu: {$backupFile}");
        $this->newLine();
    }
}