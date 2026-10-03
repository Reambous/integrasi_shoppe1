<?php

namespace App\Http\Controllers;

use App\Models\ShopeeToken;
use App\Services\ShopeeSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeAuthController extends Controller
{
    /**
     * Redirect seller ke halaman otorisasi Shopee Sandbox.
     * GET /api/shopee/auth/redirect
     */
    public function redirect(ShopeeSignatureService $signer)
    {
        try {
            if (! config('shopee.partner_id') || ! config('shopee.partner_key')) {
                Log::warning('Shopee auth redirect dibatalkan: kredensial belum diisi.');

                return response()->json([
                    'message' => 'Shopee Partner ID / Key belum dikonfigurasi di .env.',
                ], 503);
            }

            $url = $signer->buildAuthUrl();

            Log::info('Shopee auth redirect generated.');

            return redirect()->away($url);
        } catch (\Throwable $e) {
            Log::error('Shopee auth redirect gagal.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal membuat URL otorisasi Shopee.'], 500);
        }
    }

    /**
     * Callback dari Shopee: ?code=xxx&shop_id=yyy
     * Tukar code menjadi access_token + refresh_token.
     * GET /api/shopee/callback
     */
    public function callback(Request $request, ShopeeSignatureService $signer)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'shop_id' => 'required|integer',
        ]);

        $code = $validated['code'];
        $shopId = $validated['shop_id'];

        try {
            if (! config('shopee.partner_id') || ! config('shopee.partner_key')) {
                return response()->json([
                    'message' => 'Shopee Partner ID / Key belum dikonfigurasi di .env.',
                ], 503);
            }

            $timestamp = time();
            $path = (string) config('shopee.paths.get_token', '/api/v2/auth/token/get');
            $baseUrl = rtrim((string) config('shopee.api_base_url'), '/');
            $partnerId = (int) config('shopee.partner_id');

            $sign = $signer->signPublic($path, $timestamp);

            // Hormati rate limit: timeout pendek + retry 1x dengan jeda.
            $response = Http::timeout(15)
                ->retry(1, 500)
                ->withQueryParameters([
                    'partner_id' => $partnerId,
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                ])
                ->post($baseUrl.$path, [
                    'partner_id' => $partnerId,
                    'code' => $code,
                    'shop_id' => (int) $shopId,
                ]);

            if (! $response->successful()) {
                Log::error('Shopee get token gagal (HTTP error).', [
                    'shop_id' => $shopId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Gagal menukar code Shopee.',
                    'shopee_body' => $response->json(),
                ], 502);
            }

            $data = $response->json();

            if (($data['error'] ?? '') !== '' || empty($data['access_token'])) {
                Log::error('Shopee get token gagal (API error).', [
                    'shop_id' => $shopId,
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Shopee menolak penukaran code.',
                    'shopee_body' => $data,
                ], 502);
            }

            $now = now();
            $token = ShopeeToken::updateOrCreate(
                ['shop_id' => $shopId],
                [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? '',
                    'access_token_expire_at' => $now->copy()->addSeconds((int) ($data['expire_in'] ?? 14400)),
                    // Refresh token Shopee umumnya berumur ~30 hari.
                    'refresh_token_expire_at' => $now->copy()->addDays(30),
                ]
            );

            Log::info('Shopee token tersimpan.', ['shop_id' => $shopId]);

            return response()->json([
                'message' => 'Otorisasi Shopee berhasil.',
                'shop_id' => (int) $shopId,
                'token_id' => $token->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopee callback exception.', [
                'shop_id' => $shopId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Terjadi kesalahan saat callback Shopee.'], 500);
        }
    }
}
