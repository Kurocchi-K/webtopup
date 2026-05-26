<?php

namespace App\Console\Commands;

use App\Models\PPOB\PPOBBrand;
use App\Models\PPOB\PPOBCategory;
use App\Models\PPOB\PPOBProduct;
use Illuminate\Console\Command;

class DeleteNonGameProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:non-game-products {--force : Langsung delete tanpa konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus semua produk selain dari kategori GAME';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get GAME category
        $gameCategory = PPOBCategory::whereRaw('LOWER(name) LIKE ?', ['%game%'])->first();

        if (! $gameCategory) {
            $this->error('❌ Kategori GAME tidak ditemukan');
            return;
        }

        $this->info("Kategori GAME ditemukan: {$gameCategory->name}");

        // Get all products yang BUKAN dari kategori GAME
        $nonGameProducts = PPOBProduct::whereHas('brand', function ($query) use ($gameCategory) {
            $query->where('p_p_o_b_category_id', '!=', $gameCategory->id);
        })->get();

        $this->info("Total produk yang akan dihapus: {$nonGameProducts->count()}");

        if ($nonGameProducts->count() === 0) {
            $this->info('✓ Tidak ada produk yang perlu dihapus');
            return;
        }

        // Show preview
        $this->line("\n📋 Preview produk yang akan dihapus:");
        $nonGameProducts->each(function ($product) {
            $this->line("  - {$product->name} (SKU: {$product->sku}) - Brand: {$product->brand->name}");
        });

        // Confirm if not force
        if (! $this->option('force')) {
            if (! $this->confirm("\n⚠️  Lanjutkan menghapus {$nonGameProducts->count()} produk?")) {
                $this->info('Dibatalkan');
                return;
            }
        }

        // Get affected brands before deletion
        $affectedBrandIds = $nonGameProducts->pluck('p_p_o_b_brand_id')->unique()->toArray();

        // Delete products
        $nonGameProducts->each(function ($product) {
            $product->delete();
            $this->line("🗑️  Dihapus: {$product->name}");
        });

        $this->info("✓ {$nonGameProducts->count()} produk berhasil dihapus");

        // Deactivate brands yang tidak punya produk aktif
        $this->deactivateEmptyBrands($affectedBrandIds);
    }

    /**
     * Deactivate brands that have no active products.
     */
    private function deactivateEmptyBrands(array $brandIds): void
    {
        $brandDeactivatedCount = 0;

        foreach ($brandIds as $brandId) {
            $brand = PPOBBrand::find($brandId);

            if (! $brand) {
                continue;
            }

            // Check if brand has active products
            $activeProductCount = PPOBProduct::where('p_p_o_b_brand_id', $brandId)
                ->count();

            // If no active products and brand is still active, deactivate it
            if ($activeProductCount === 0 && $brand->status) {
                $brand->update(['status' => false]);
                $brandDeactivatedCount++;
                $this->line("⊘  Brand Dinonaktifkan: {$brand->name}");
            }
        }

        if ($brandDeactivatedCount > 0) {
            $this->info("✓ {$brandDeactivatedCount} brand berhasil dinonaktifkan");
        }

        // Deactivate categories yang tidak punya brand aktif
        $this->deactivateEmptyCategories();
    }

    /**
     * Deactivate categories that have no active brands.
     */
    private function deactivateEmptyCategories(): void
    {
        $categoryDeactivatedCount = 0;

        $categories = PPOBCategory::all();

        foreach ($categories as $category) {
            // Check if category has active brands
            $activeBrandCount = PPOBBrand::where('p_p_o_b_category_id', $category->id)
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
            $this->info("✓ {$categoryDeactivatedCount} category berhasil dinonaktifkan");
        }
    }
}
