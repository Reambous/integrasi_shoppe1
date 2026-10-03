<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeToken extends Model
{
    protected $fillable = [
        'shop_id',
        'access_token',
        'refresh_token',
        'access_token_expire_at',
        'refresh_token_expire_at',
    ];

    protected $casts = [
        // Enkripsi otomatis saat disimpan (credential protection).
        // Kolom DB bertipe TEXT agar muat hasil enkripsi.
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expire_at' => 'datetime',
        'refresh_token_expire_at' => 'datetime',
    ];

    /**
     * Cek apakah access token sudah (hampir) kedaluwarsa.
     * $bufferSeconds memberi jeda agar refresh tidak mepet.
     */
    public function isAccessTokenExpired(int $bufferSeconds = 300): bool
    {
        if (! $this->access_token_expire_at) {
            return true;
        }

        return $this->access_token_expire_at
            ->subSeconds($bufferSeconds)
            ->isPast();
    }
}
