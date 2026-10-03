<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopee_tokens', function (Blueprint $table) {
            $table->id();
            // ID toko dari Shopee (unik, satu baris per toko)
            $table->unsignedBigInteger('shop_id')->unique();
            // Token disimpan sebagai TEXT agar tidak terpotong;
            // enkripsi/dekripsi ditangani di level Model/Service.
            $table->text('access_token');
            $table->text('refresh_token');
            // Waktu kedaluwarsa token (expiries) untuk auto-refresh via scheduler
            $table->timestamp('access_token_expire_at')->nullable();
            $table->timestamp('refresh_token_expire_at')->nullable();
            $table->timestamps();

            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopee_tokens');
    }
};
