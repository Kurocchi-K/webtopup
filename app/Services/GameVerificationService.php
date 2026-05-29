<?php

namespace App\Services;

use App\Services\Interfaces\GameVerificationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GameVerificationService implements GameVerificationInterface
{
    public function resolveAccount(string $game, string $uid, ?string $server): array
    {
        if (preg_match('/^(\d+)\s*\(\s*(\d+)\s*\)$/', $uid, $matches)) {
            $uid = $matches[1];
            $server = $matches[2];
        }

        $merchantId = config('services.apigames.merchant_id');
        $secretKey = config('services.apigames.secret_key');

        // 1. Bersihkan input (Hapus spasi, buat lowercase)
        $normalized = strtolower(str_replace(' ', '', $game));

        // 2. Mapping ke kode API
        $gameMap = [
            'mobilelegends' => 'mobilelegend',
            // Tambahkan game lain di sini jika perlu
        ];

        $gameCode = $gameMap[$normalized] ?? $normalized;

        $signature = md5($merchantId . $secretKey);
        $url = "https://v1.apigames.id/merchant/{$merchantId}/cek-username/{$gameCode}";

        $response = Http::get($url, [
            'user_id'   => $uid,
            'zone_id'   => $server,
            'signature' => $signature,
        ]);

        $result = $response->json();
        Log::info('Respon API Apigames (FINAL):', ['url' => $url, 'response' => $result]);

        if (isset($result['status']) && $result['status'] == 1) {
            return [
                'status' => true,
                'data' => ['username' => $result['data']['username'] ?? 'Tidak ditemukan']
            ];
        }

        return ['status' => false, 'message' => $result['message'] ?? 'Data Not Found'];
    }
}
