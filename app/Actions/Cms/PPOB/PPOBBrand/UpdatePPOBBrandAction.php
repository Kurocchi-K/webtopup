<?php

namespace App\Actions\Cms\PPOB\PPOBBrand;

use App\Models\PPOB\PPOBBrand;
use App\Traits\WithMediaCollection;
use Illuminate\Http\UploadedFile;

class UpdatePPOBBrandAction
{
    use WithMediaCollection;

    /**
     * Handle the action.
     */
    public function handle(PPOBBrand $brand, array $data): bool
    {
        // 1. Simpan Media (Dengan perbaikan pengecekan instanceof)
        if (($data['image'] ?? null) instanceof UploadedFile) {
            $this->saveMedia($brand, $data['image'], 'image');
        }

        if (($data['banner'] ?? null) instanceof UploadedFile) {
            $this->saveMedia($brand, $data['banner'], 'banner');
        }

        if (($data['default_product_image'] ?? null) instanceof UploadedFile) {
            $this->saveMedia($brand, $data['default_product_image'], 'default_product_image');
        }

        // 2. Sinkronisasi Provider Produk
        // Jika provider berubah, update juga semua produk terkait
        if (isset($data['provider']) && $data['provider'] !== $brand->provider) {
            $brand->products()->where('provider', $brand->provider)->update([
                'provider' => $data['provider'],
            ]);
        }

        // 3. Update Model (Bersihkan data file agar tidak error kolom database)
        // Gunakan 'except' agar file tidak ikut disimpan ke kolom DB
        $updateData = collect($data)->except(['image', 'banner', 'default_product_image'])->toArray();

        return $brand->update($updateData);
    }
}
