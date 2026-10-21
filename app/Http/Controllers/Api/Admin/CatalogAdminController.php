<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogAdminController extends ApiController
{
    // ---------- Kategori ----------
    public function categories()
    {
        return $this->ok(Category::withCount('products')->orderBy('sort_order')->get());
    }

    public function categoryStore(Request $r)
    {
        $d = $this->catRules($r);
        $d['slug'] = $this->uniqueSlug(Category::class, $d['name']);
        $c = Category::create($d);
        AdminActivityLog::record($r->user(), 'category.create', 'category:'.$c->id);

        return $this->ok($c, 'Kategori dibuat.', 201);
    }

    public function categoryUpdate(Request $r, Category $category)
    {
        $category->update($this->catRules($r));
        AdminActivityLog::record($r->user(), 'category.update', 'category:'.$category->id);

        return $this->ok($category, 'Kategori diperbarui.');
    }

    public function categoryDestroy(Request $r, Category $category)
    {
        if ($category->products()->exists()) {
            return $this->fail('Kategori masih memiliki produk. Nonaktifkan saja.', 422);
        }
        $category->delete();
        AdminActivityLog::record($r->user(), 'category.delete', 'category:'.$category->id);

        return $this->ok(null, 'Kategori dihapus.');
    }

    private function catRules(Request $r): array
    {
        return $r->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:8'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]) + ['sort_order' => 0];
    }

    // ---------- Produk ----------
    public function products(Request $r)
    {
        $q = Product::with('category:id,name')->withCount(['variants', 'orders']);
        if ($s = trim((string) $r->query('q'))) {
            $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%');
        }

        return $this->paginated($q->orderByDesc('id')->paginate(15), fn ($p) => $p->toArray() + ['image_url' => $p->imageUrl()]);
    }

    public function productShow(Product $product)
    {
        $product->load('variants');

        return $this->ok($product->toArray() + ['image_url' => $product->imageUrl()]);
    }

    public function productStore(Request $r)
    {
        $d = $this->productRules($r);
        $d['slug'] = $this->uniqueSlug(Product::class, $d['name']);
        $p = Product::create($d);
        AdminActivityLog::record($r->user(), 'product.create', 'product:'.$p->id, ['name' => $p->name]);

        return $this->ok($p, 'Produk dibuat.', 201);
    }

    public function productUpdate(Request $r, Product $product)
    {
        $product->update($this->productRules($r));
        AdminActivityLog::record($r->user(), 'product.update', 'product:'.$product->id);

        return $this->ok($product, 'Produk diperbarui.');
    }

    public function productDestroy(Request $r, Product $product)
    {
        if ($product->orders()->exists()) {
            $product->update(['is_active' => false]);
            AdminActivityLog::record($r->user(), 'product.archive', 'product:'.$product->id);

            return $this->ok(null, 'Produk sudah memiliki transaksi, jadi dinonaktifkan (diarsipkan), tidak dihapus.');
        }
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        AdminActivityLog::record($r->user(), 'product.delete', 'product:'.$product->id);

        return $this->ok(null, 'Produk dihapus.');
    }

    public function productImage(Request $r, Product $product)
    {
        $r->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $r->file('image')->store('products', 'public');
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->update(['image' => $path]);
        AdminActivityLog::record($r->user(), 'product.image', 'product:'.$product->id);

        return $this->ok(['image_url' => $product->imageUrl()], 'Gambar diperbarui.');
    }

    private function productRules(Request $r): array
    {
        $d = $r->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'needs_user_id' => ['boolean'],
            'needs_server_id' => ['boolean'],
            'user_id_label' => ['nullable', 'string', 'max:40'],
            'server_id_label' => ['nullable', 'string', 'max:40'],
            'user_id_hint' => ['nullable', 'string', 'max:255'],
            'provider_code' => ['nullable', 'string', 'max:60'],
            'is_popular' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $d['user_id_label'] = $d['user_id_label'] ?? 'User ID';
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }

    // ---------- Nominal ----------
    public function variants(Product $product)
    {
        return $this->ok($product->variants()->get());
    }

    public function variantStore(Request $r, Product $product)
    {
        $v = $product->variants()->create($this->variantRules($r));
        AdminActivityLog::record($r->user(), 'variant.create', 'variant:'.$v->id, ['product' => $product->id]);

        return $this->ok($v, 'Nominal ditambahkan.', 201);
    }

    public function variantUpdate(Request $r, ProductVariant $variant)
    {
        $variant->update($this->variantRules($r));
        AdminActivityLog::record($r->user(), 'variant.update', 'variant:'.$variant->id);

        return $this->ok($variant, 'Nominal diperbarui.');
    }

    public function variantDestroy(Request $r, ProductVariant $variant)
    {
        if (\App\Models\Order::where('product_variant_id', $variant->id)->exists()) {
            $variant->update(['is_active' => false]);

            return $this->ok(null, 'Nominal sudah dipakai transaksi, jadi dinonaktifkan.');
        }
        $variant->delete();
        AdminActivityLog::record($r->user(), 'variant.delete', 'variant:'.$variant->id);

        return $this->ok(null, 'Nominal dihapus.');
    }

    private function variantRules(Request $r): array
    {
        $d = $r->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'original_price' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'provider_sku' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $d['sort_order'] = $d['sort_order'] ?? 0;

        return $d;
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
