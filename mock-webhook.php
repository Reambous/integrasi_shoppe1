<?php
/*
 * Kirim mock payload webhook Shopee ke server lokal dan verifikasi DB.
 * Cara pakai (server `php artisan serve` harus sudah jalan):
 *   php mock-webhook.php                -> kirim signature VALID (harap 200 + baris DB)
 *   php mock-webhook.php --invalid      -> kirim signature SALAH (harap 403, tanpa baris baru)
 *   php mock-webhook.php --url=http://127.0.0.1:8000/api/shopee/webhook
 *
 * Signature = HMAC-SHA256( fullUrl + '|' + rawBody, partner_key ).
 * Partner key dibaca dari config (== .env SHOPEE_PARTNER_KEY).
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$url = 'http://127.0.0.1:8000/api/shopee/webhook';
$invalid = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--url=')) {
        $url = substr($arg, 6);
    }
    if ($arg === '--invalid') {
        $invalid = true;
    }
}

$key = (string) config('shopee.partner_key');
if ($key === '') {
    echo "GAGAL: SHOPEE_PARTNER_KEY masih kosong di .env. Isi dulu (dummy boleh), restart serve.\n";
    exit(1);
}

// Mock payload mirip push order-status (code 3) dari Shopee.
$body = json_encode([
    'code' => 3,
    'shop_id' => 12345,
    'timestamp' => time(),
    'data' => ['order_sn' => 'MOCK'.date('His'), 'order_status' => 'READY_TO_SHIP'],
]);

$sig = $invalid ? 'salah' : hash_hmac('sha256', $url.'|'.$body, $key);
echo 'URL : '.$url.PHP_EOL;
echo 'Mode: '.($invalid ? 'INVALID (harap 403)' : 'VALID (harap 200)').PHP_EOL;

$before = App\Models\WebhookLog::count();

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: '.$sig]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP: '.$http.PHP_EOL;
echo 'BODY: '.$res.PHP_EOL;

$after = App\Models\WebhookLog::count();
echo "Baris webhook_logs: sebelum=$before sesudah=$after".PHP_EOL;

if (! $invalid && $after > $before) {
    $last = App\Models\WebhookLog::orderByDesc('id')->first();
    echo "Terakhir: id={$last->id} event={$last->event_type} shop={$last->shop_id} status={$last->status}".PHP_EOL;
}
