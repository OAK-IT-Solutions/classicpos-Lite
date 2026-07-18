<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/categories")]
class CategoryController extends Controller
{
    #[OA\Get(path: "/categories", tags: ["Categories"], summary: "List all categories", responses: [new OA\Response(response: 200, description: "List of categories")])]
    public function index()
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(),
        ]);
    }

    #[OA\Post(path: "/categories", tags: ["Categories"], summary: "Create a category", responses: [new OA\Response(response: 201, description: "Category created")])]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'returnable' => 'nullable|boolean',
            'parent_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $validated['returnable'] = $validated['returnable'] ?? false;

        $category = Category::create($validated);

        return response()->json(['data' => $category], 201);
    }

    #[OA\Put(path: "/categories/{id}", tags: ["Categories"], summary: "Update a category", responses: [new OA\Response(response: 200, description: "Category updated")])]
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:categories,name,' . $id,
            'returnable' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json(['data' => $category]);
    }

    #[OA\Get(path: "/categories/{id}", tags: ["Categories"], summary: "Get a category", responses: [new OA\Response(response: 200, description: "Category details")])]
    public function show(string $id)
    {
        $category = Category::with('products')->findOrFail($id);

        return response()->json(['data' => $category]);
    }

    #[OA\Delete(path: "/categories/{id}", tags: ["Categories"], summary: "Delete a category", responses: [new OA\Response(response: 200, description: "Category deleted")])]
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);

        if ($category->products()->exists()) {
            return response()->json([
                'error' => ['code' => 'ERR_CATEGORY_HAS_PRODUCTS', 'message' => 'Cannot delete category with associated products.'],
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
