<?php

namespace App\Actions\Main;

use App\Models\PPOB\PPOBBrand;
use App\Models\PPOB\PPOBProduct;
use App\Services\Interfaces\GameVerificationInterface; // Panggil Interface-nya!

class CheckGameAccountAction
{
    // Menggunakan Interface (bukan GameProService)
    public function __construct(
        protected GameVerificationInterface $verificationService,
    ) {}

    public function handle(array $data): array
    {
        $brandName = '';

        if (isset($data['product_id'])) {
            $product = PPOBProduct::find($data['product_id']);
            $brandName = $product->brand->name;
        } elseif (isset($data['slug'])) {
            $brand = PPOBBrand::where('slug', $data['slug'])->firstOrFail();
            $brandName = $brand->name;
        }

        // HAPUS blok IF "Mobile Legend" yang tadi
        // Biarkan Service yang menentukan apakah game ini didukung atau tidak.

        return $this->verificationService->resolveAccount(
            game: $brandName, // Kirim nama brand-nya, biar Service yang memetakan
            uid: $data['account_id'],
            server: $data['server_id'] ?? null,
        );
    }
}
