<?php

namespace App\Actions\Cms\Order\Order;

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\DigiflazzService;

class StoreOrderAction
{
    public function handle(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Generate Reference Code Unik
            $data['reference'] = 'BJ-' . strtoupper(Str::random(10));
            $data['topup_status'] = 0; // 0 = Pending

            // 2. Simpan ke Database (CUKUP SEKALI SAJA)
            $order = Order::create($data);

            // 3. Logic ke Provider
            try {
                $digi = new DigiflazzService();

                // PASTIKAN method 'order' ini ada di DigiflazzService Abang
                $response = $digi->order($order);

                if (isset($response['status']) && $response['status'] === 'success') {
                    $order->update(['topup_status' => 1]); // On Progress
                } else {
                    $order->update(['topup_status' => 3]); // Failed
                }
            } catch (\Exception $e) {
                // Jika API gagal konek
                $order->update(['topup_status' => 3]);
            }

            // 4. RETURN OBJEK YANG SUDAH DIBUAT (Bukan buat baru lagi)
            return $order;
        });
    }
}
