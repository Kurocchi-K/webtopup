<?php

namespace App\Console\Commands;

use App\Models\PPOB\PPOBBrand;
use App\Models\PPOB\PPOBCategory;
use App\Models\PPOB\PPOBProduct;
use Illuminate\Console\Command;

class SyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:products {--force-delete : Hapus produk yang tidak ada di Digiflazz (default: deactive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi produk dari Digiflazz ke database dan hapus produk yang sudah tidak ada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sedang sinkronisasi data dari Digiflazz...');

        // Memanggil Class Action untuk import produk baru
        app(\App\Actions\Cms\PPOB\ImportDigiflazz\SyncPPOBProductAction::class)->handle();

        // Deactivate produk yang tidak ada di Digiflazz lagi
        $this->deactivateDeletedProducts();

        $this->info('✓ Sinkronisasi selesai!');
    }

    /**
     * Deactivate or delete products that are no longer in Digiflazz.
     */
    private function deactivateDeletedProducts(): void
    {
        $forceDelete = $this->option('force-delete');
        $digiflazzService = app(\App\Services\DigiflazzService::class);

        // Get all products from Digiflazz
        $digiflazzProducts = $digiflazzService->getPrepaidProducts(refresh: true);
        $digiflazzSkus = collect($digiflazzProducts)->pluck('buyer_sku_code')->toArray();

        // SAFETY CHECK: Jika API return 0 produk tapi database masih aktif, abort
        if (empty($digiflazzSkus)) {
            $activeProductCount = PPOBProduct::where('provider', 'digiflazz')
                ->where('status', true)
                ->count();

            if ($activeProductCount > 0) {
                $this->error('❌ ABORT! API Digiflazz tidak return produk (mungkin API Key expired atau issue)');
                $this->error("Database masih punya {$activeProductCount} produk aktif");
                $this->error('Cek .env DIGIFLAZZ_USERNAME dan DIGIFLAZZ_API_KEY');
                return;
            }
        }

        // Get all active products from database
        $dbProducts = PPOBProduct::where('provider', 'digiflazz')
            ->where('status', true)
            ->get();

        $deletedCount = 0;
        $deactivatedCount = 0;
        $affectedBrands = [];

        foreach ($dbProducts as $product) {
            if (! in_array($product->sku, $digiflazzSkus)) {
                if ($forceDelete) {
                    $affectedBrands[] = $product->p_p_o_b_brand_id;
                    $product->delete();
                    $deletedCount++;
                    $this->line("🗑️  Dihapus: {$product->name} (SKU: {$product->sku})");
                } else {
                    $affectedBrands[] = $product->p_p_o_b_brand_id;
                    $product->update(['status' => false]);
                    $deactivatedCount++;
                    $this->line("⊘  Dinonaktifkan: {$product->name} (SKU: {$product->sku})");
                }
            }
        }

        // Deactivate categories yang tidak punya brand aktif
        if (! empty($affectedBrands)) {
            $this->deactivateEmptyCategories(array_unique($affectedBrands));
        }

        if ($forceDelete && $deletedCount > 0) {
            $this->info("✓ {$deletedCount} produk berhasil dihapus");
        } elseif ($deactivatedCount > 0) {
            $this->info("✓ {$deactivatedCount} produk berhasil dinonaktifkan");
        } else {
            $this->info('✓ Semua produk masih aktif di Digiflazz');
        }
    }

    /**
     * Deactivate categories that have no active brands.
     */
    private function deactivateEmptyCategories(array $brandIds): void
    {
        // Get unique category IDs from affected brands
        $affectedCategoryIds = PPOBBrand::whereIn('id', $brandIds)
            ->distinct()
            ->pluck('p_p_o_b_category_id')
            ->toArray();

        $categoryDeactivatedCount = 0;

        foreach ($affectedCategoryIds as $categoryId) {
            $category = PPOBCategory::find($categoryId);

            if (! $category) {
                continue;
            }

            // Check if category has active brands
            $activeBrandCount = PPOBBrand::where('p_p_o_b_category_id', $categoryId)
                ->where('status', true)
                ->count();

            // If no active brands and category is still active, deactivate it
            if ($activeBrandCount === 0 && $category->status) {
                $category->update(['status' => false]);
                $categoryDeactivatedCount++;
                $this->line("⊘  Category Dinonaktifkan: {$category->name}");
            }
        }

        if ($categoryDeactivatedCount > 0) {
            $this->info("✓ {$categoryDeactivatedCount} category berhasil dinonaktifkan karena tidak punya brand aktif");
        }
    }
}
