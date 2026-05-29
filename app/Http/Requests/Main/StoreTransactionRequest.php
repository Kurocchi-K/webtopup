<?php

namespace App\Http\Requests\Main;

use App\Models\PPOB\PPOBProduct;
use App\Services\Interfaces\GameVerificationInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Bersihkan spasi di awal dan akhir
        $accId = trim($this->account_id);
        $serverId = trim($this->server_id);

        // LOGIKA PARSING "APAPUN FORMATNYA":
        // Kita coba cari pola angka(angka)
        if (preg_match('/(\d+)\s*[\(\)]+\s*(\d+)/', $accId, $matches)) {
            // Jika ketemu pola, pisahkan ID dan Server
            $this->merge([
                'account_id' => $matches[1],
                'server_id'  => $serverId ?: $matches[2], // Jika server_id input kosong, ambil dari regex
            ]);
        } else {
            // Jika tidak ketemu pola (user cuma masukin ID saja), tetap gunakan apa adanya
            $this->merge([
                'account_id' => $accId,
                'server_id'  => $serverId,
            ]);
        }

        \Log::info('Data Checkout Setelah Parsing:', [
            'account_id' => $this->account_id,
            'server_id'  => $this->server_id
        ]);
    }

    protected function passedValidation(): void
    {
        $product = PPOBProduct::find($this->product_id);
        $verificationService = app(\App\Services\Interfaces\GameVerificationInterface::class);

        // Cek apakah brand butuh verifikasi (Mobile Legends)
        if (Str::contains(strtolower($product->brand->name), 'mobile legend')) {

            $resolve = $verificationService->resolveAccount(
                game: 'mobilelegend',
                uid: $this->account_id,
                server: $this->server_id,
            );

            // Jika API pusat bilang tidak ditemukan, baru lempar error
            if (!($resolve['status'] ?? false)) {
                \Log::error('Gagal Verifikasi saat Checkout:', $resolve);
                throw ValidationException::withMessages([
                    'account_id' => 'ID atau Server tidak valid (Silakan cek kembali)',
                ]);
            }
        }

        // Gabungkan data untuk Action
        $this->merge([
            'p_p_o_b_brand_id' => $product->p_p_o_b_brand_id,
            'p_p_o_b_product_id' => $product->id,
            'submited' => [
                'account_id' => $this->account_id,
                'server_id' => $this->server_id,
            ],
        ]);
    }
}
