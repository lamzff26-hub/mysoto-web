<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Daftar produk aktif untuk halaman kasir. Mendukung pencarian
     * (nama/SKU) dan filter kategori. Dipaginasi agar ringan di mobile.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('category')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim($request->string('search'));
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->when($request->filled('category_id'), fn ($q) => $q
                ->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return ProductResource::collection($products);
    }
}
