<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;

class PosProductService
{
    public function getProducts(string $branchId, ?string $categoryName = null, ?string $search = null): array
    {
        $warehouseIds = Warehouse::where('branch_id', $branchId)
            ->where('is_active', true)
            ->pluck('id');

        $query = Product::with('category')->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryName) {
            $category = Category::where('name', $categoryName)->first();
            if ($category) {
                $categoryIds = $this->getCategoryWithDescendantIds($category->id);
                $query->whereIn('category_id', $categoryIds);
            }
        }

        $products = $query->orderBy('name')->get();

        $inventoryLookup = Inventory::whereIn('warehouse_id', $warehouseIds)
            ->get()
            ->groupBy('product_id')
            ->map(fn($items) => $items->sum('quantity'));

        $result = $products->map(fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'price' => (float) $product->price,
            'category' => $product->category?->name,
            'stock_uom' => $product->stock_uom,
            'stock' => (float) ($inventoryLookup->get($product->id) ?? 0),
            'image' => $product->image,
        ]);

        $categories = Category::orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return [
            'data' => $result,
            'categories' => $categories,
        ];
    }

    private function getCategoryWithDescendantIds(string $categoryId): array
    {
        $ids = [$categoryId];

        $children = Category::where('parent_id', $categoryId)->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getCategoryWithDescendantIds($childId));
        }

        return $ids;
    }
}
