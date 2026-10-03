<?php

namespace App\Console\Commands;

use App\Models\ShopeeToken;
use App\Services\ShopeeSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Refresh access_token Shopee yang (hampir) kedaluwarsa.
 * Dijalankan via scheduler + Cron cPanel tiap 5 menit.
 * Tidak memakai queue worker / daemon (aturan cPanel).
 */
class RefreshShopeeTokens extends Command
{
    protected $signature = 'shopee:refresh-tokens {--limit=20 : Maksimal token per jalan}';
    protected $description = 'Refresh access token Shopee yang hampir kedaluwarsa';

    public function handle(ShopeeSignatureService $signer): int
    {
        if (! config('shopee.partner_id') || ! config('shopee.partner_key')) {
            $this->warn('Shopee Partner ID / Key belum dikonfigurasi, dilewati.');
            Log::warning('shopee:refresh-tokens dilewati: kredensial belum diisi.');

            return self::SUCCESS;
        }

        $tokens = ShopeeToken::query()
            ->where(function ($q) {
                $q->whereNull('access_token_expire_at')
                    ->orWhere('access_token_expire_at', '<=', now()->addMinutes(10));
            })
            ->limit((int) $this->option('limit'))
            ->get();

        if ($tokens->isEmpty()) {
            $this->info('Tidak ada token yang perlu di-refresh.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($tokens as $token) {
            try {
                if ($this->refreshOne($signer, $token)) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::error('shopee:refresh-tokens exception.', [
                    'shop_id' => $token->shop_id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Jeda antar panggilan agar hormat pada rate limit Shopee.
            sleep(1);
        }

        $this->info("Refresh selesai: {$ok} ok, {$fail} gagal.");

        return self::SUCCESS;
    }

    protected function refreshOne(ShopeeSignatureService $signer, ShopeeToken $token): bool
    {
        $timestamp = time();
        $path = (string) config('shopee.paths.refresh_token', '/api/v2/auth/access_token/get');
        $baseUrl = rtrim((string) config('shopee.api_base_url'), '/');
        $partnerId = (int) config('shopee.partner_id');

        $sign = $signer->signPublic($path, $timestamp);

        $response = Http::timeout(15)
            ->retry(1, 500)
            ->withQueryParameters([
                'partner_id' => $partnerId,
                'timestamp' => $timestamp,
                'sign' => $sign,
            ])
            ->post($baseUrl.$path, [
                'partner_id' => $partnerId,
                'shop_id' => (int) $token->shop_id,
                'refresh_token' => $token->refresh_token,
            ]);

        if (! $response->successful()) {
            Log::error('Refresh token Shopee gagal (HTTP error).', [
                'shop_id' => $token->shop_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $data = $response->json();

        if (($data['error'] ?? '') !== '' || empty($data['access_token'])) {
            Log::error('Refresh token Shopee gagal (API error).', [
                'shop_id' => $token->shop_id,
                'body' => $response->body(),
            ]);

            return false;
        }

        $now = now();
        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'access_token_expire_at' => $now->copy()->addSeconds((int) ($data['expire_in'] ?? 14400)),
        ]);

        Log::info('Shopee token di-refresh.', ['shop_id' => $token->shop_id]);

        return true;
    }
}
