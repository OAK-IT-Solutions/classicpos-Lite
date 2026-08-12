<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/products")]
class ProductController extends BaseController
{
    protected string $modelClass = Product::class;

    protected array $searchableFields = ['name', 'barcode'];

    protected array $withRelations = ['category'];

    protected function rules(Request $request, ?string $id = null): array
    {
        $uniqueBarcode = 'unique:products,barcode' . ($id ? ',' . $id : '');

        return [
            'name' => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'barcode' => 'nullable|string|' . $uniqueBarcode,
            'price' => ($id ? 'sometimes|' : '') . 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'stock_uom' => 'nullable|string|max:50',
            'min_stock' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:2048',
            'returnable' => 'nullable|boolean',
        ];
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        $validated['is_active'] = true;

        return $validated;
    }

    protected function additionalQuery(Request $request, $query): void
    {
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'inventory.warehouse'])->findOrFail($id);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $record = Product::findOrFail($id);
        $record->update(['is_active' => false]);

        return response()->json(['message' => 'Product deactivated successfully.'], 200);
    }

    public function byBarcode(string $barcode): JsonResponse
    {
        $product = Product::with('category')
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_NOT_FOUND',
                    'message' => 'No product found with this barcode.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 404);
        }

        $inventory = $product->inventory()->sum('quantity');

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'price' => $product->price,
                'image' => $product->image,
                'stock' => $inventory,
                'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
            ],
        ]);
    }

    public function uploadImage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $product = Product::findOrFail($id);

        if ($product->image && str_starts_with($product->image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $product->image));
        }

        $path = $request->file('image')->store('products', 'public');

        $product->update(['image' => '/storage/' . $path]);

        return response()->json(['data' => $product->fresh()->load('category')]);
    }

    public function deleteImage(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->image && str_starts_with($product->image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $product->image));
        }

        $product->update(['image' => null]);

        return response()->json(['data' => $product->fresh()->load('category')]);
    }
}
