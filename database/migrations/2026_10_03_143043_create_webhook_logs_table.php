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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            // Jenis event push dari Shopee (mis. code 3 = order status update)
            $table->string('event_type', 100)->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            // Raw JSON payload memakai LONGTEXT agar kompatibel
            // dengan MySQL lama di shared hosting dan mudah di-export via phpMyAdmin.
            $table->longText('payload');
            // Status pemrosesan oleh scheduler: pending -> processed / failed
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('shop_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
