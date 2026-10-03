<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use App\Services\ShopeeSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    /**
     * Terima Push Shopee, verifikasi HMAC, simpan mentah, balas 200 cepat.
     * POST /api/shopee/webhook
     *
     * Aturan cPanel: proses sinkron minimal (validasi + insert),
     * pengolahan berat dilakukan scheduler via tabel webhook_logs.
     */
    public function handle(Request $request, ShopeeSignatureService $signer)
    {
        $rawBody = $request->getContent();
        $authorization = $request->header('Authorization');

        try {
            if (! $this->isValid($request, $signer, $rawBody, $authorization)) {
                Log::warning('Shopee webhook ditolak: signature invalid.', [
                    'url' => $request->fullUrl(),
                ]);

                return response()->json(['message' => 'Invalid signature.'], 403);
            }

            $data = json_decode($rawBody, true);
            if (! is_array($data)) {
                Log::warning('Shopee webhook ditolak: body bukan JSON valid.');

                return response()->json(['message' => 'Invalid JSON payload.'], 400);
            }

            WebhookLog::create([
                'event_type' => isset($data['code']) ? (string) $data['code'] : 'unknown',
                'shop_id' => $data['shop_id']
                    ?? $data['data']['shop_id']
                    ?? $data['shop_id_list'][0]
                    ?? null,
                'payload' => $data,
                'status' => WebhookLog::STATUS_PENDING,
            ]);

            // Balas 200 segera agar Shopee tidak retry.
            return response()->json(['received' => true]);
        } catch (\Throwable $e) {
            Log::error('Shopee webhook gagal disimpan.', [
                'error' => $e->getMessage(),
                'body' => mb_substr($rawBody ?: '', 0, 2000),
            ]);

            // Tetap 200 + catat gagal? Tidak — kembalikan 500 agar Shopee
            // retry, KECUALI insert gagal karena DB: tetap 500 supaya ketahuan.
            return response()->json(['message' => 'Webhook handling failed.'], 500);
        }
    }

    /**
     * Verifikasi memakai fullUrl dulu (cocok saat via Ngrok/prod),
     * fallback ke url() tanpa query bila Shopee sign tanpa query string.
     */
    protected function isValid(
        Request $request,
        ShopeeSignatureService $signer,
        string $rawBody,
        ?string $authorization
    ): bool {
        if (empty($authorization)) {
            return false;
        }

        if ($signer->verifyPush($request->fullUrl(), $rawBody, $authorization)) {
            return true;
        }

        return $signer->verifyPush($request->url(), $rawBody, $authorization);
    }
}
