<?php

namespace App\Services;

/**
 * ShopeeSignatureService
 *
 * Menghasilkan & memverifikasi HMAC-SHA256 sesuai Shopee Open Platform v2:
 *  - Public API : partner_id + api_path + timestamp
 *  - Shop API   : partner_id + api_path + timestamp + access_token + shop_id
 *  - Push verify: url + '|' + raw_body  (Authorization header)
 *
 * api_path hanya path tanpa host, mis. /api/v2/shop/auth_partner.
 */
class ShopeeSignatureService
{
    public function __construct(
        protected string|int $partnerId = '',
        protected string $partnerKey = ''
    ) {
        if ($partnerId === '' || $partnerKey === '') {
            $this->partnerId = (string) config('shopee.partner_id');
            $this->partnerKey = (string) config('shopee.partner_key');
        }
    }

    /** Signature untuk Public API (auth link, get/refresh token). */
    public function signPublic(string $path, int $timestamp): string
    {
        return $this->sign($this->partnerId.$path.$timestamp);
    }

    /** Signature untuk Shop API (butuh access_token + shop_id). */
    public function signShop(string $path, int $timestamp, string $accessToken, string|int $shopId): string
    {
        return $this->sign($this->partnerId.$path.$timestamp.$accessToken.$shopId);
    }

    /** Bangun URL otorisasi untuk redirect seller ke Shopee. */
    public function buildAuthUrl(?int $timestamp = null, ?string $redirectUrl = null): string
    {
        $timestamp ??= time();
        $path = (string) config('shopee.paths.auth_partner', '/api/v2/shop/auth_partner');
        $baseUrl = rtrim((string) config('shopee.auth_url'), '/');

        // auth_url di config sudah full URL; jika hanya host, tambahkan path.
        $host = str_contains($baseUrl, '/api/') ? $baseUrl : $baseUrl.$path;
        $redirect = $redirectUrl ?: (string) config('shopee.redirect_url');
        $sign = $this->signPublic($path, $timestamp);

        return $host.'?'.http_build_query([
            'partner_id' => $this->partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $redirect,
        ]);
    }

    /**
     * Verifikasi signature Push/Webhook dari Shopee.
     * base_string = url + '|' + raw_body, HMAC-SHA256 dengan partner_key.
     * Gunakan hash_equals agar timing-safe. Tolak jika kosong.
     */
    public function verifyPush(string $url, string $rawBody, ?string $authorization): bool
    {
        if ($authorization === null || $authorization === '' || $this->partnerKey === '') {
            return false;
        }

        $expected = $this->sign($url.'|'.$rawBody);

        return hash_equals(strtolower($expected), strtolower(trim($authorization)));
    }

    protected function sign(string $baseString): string
    {
        return hash_hmac('sha256', $baseString, $this->partnerKey);
    }
}
