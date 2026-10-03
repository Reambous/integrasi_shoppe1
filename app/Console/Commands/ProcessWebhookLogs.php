<?php

namespace App\Console\Commands;

use App\Models\WebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Proses webhook_logs berstatus pending menjadi processed/failed.
 * Dijalankan via scheduler + Cron cPanel tiap 5 menit.
 *
 * Tahap ini skeleton: validasi struktur + penanda status.
 * Logika bisnis per event (order update, dsb.) ditambahkan bertahap.
 */
class ProcessWebhookLogs extends Command
{
    protected $signature = 'shopee:process-webhooks {--limit=50 : Maksimal baris per jalan}';
    protected $description = 'Proses webhook_logs pending dari Shopee';

    public function handle(): int
    {
        $logs = WebhookLog::query()
            ->where('status', WebhookLog::STATUS_PENDING)
            ->oldest()
            ->limit((int) $this->option('limit'))
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Tidak ada webhook pending.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($logs as $log) {
            try {
                $this->processOne($log);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $log->update([
                    'status' => WebhookLog::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                    'processed_at' => now(),
                ]);
                Log::error('shopee:process-webhooks gagal pada satu baris.', [
                    'id' => $log->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Proses webhook selesai: {$ok} ok, {$fail} gagal.");

        return self::SUCCESS;
    }

    protected function processOne(WebhookLog $log): void
    {
        $payload = $log->payload;

        // Payload disimpan via cast array; pastikan struktur minimal ada.
        if (! is_array($payload) || ! array_key_exists('code', $payload)) {
            $log->update([
                'status' => WebhookLog::STATUS_FAILED,
                'error_message' => 'Payload tidak memiliki field code.',
                'processed_at' => now(),
            ]);

            return;
        }

        // TODO: dispatch logika bisnis per $payload['code'] di sini.
        // Untuk sekarang cukup tandai processed agar tidak diproses ulang.
        Log::info('Webhook diproses.', [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'shop_id' => $log->shop_id,
        ]);

        $log->update([
            'status' => WebhookLog::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }
}
