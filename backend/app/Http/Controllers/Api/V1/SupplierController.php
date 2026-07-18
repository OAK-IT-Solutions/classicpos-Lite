<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Supplier;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/suppliers")]
class SupplierController extends BaseController
{
    protected string $modelClass = Supplier::class;

    protected array $searchableFields = ['name', 'contact_person', 'phone', 'email'];

    protected array $withRelations = [];

    protected function rules(Request $request, ?string $id = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];

        if ($id) {
            $rules['phone'] = 'required|string|max:20|unique:suppliers,phone,' . $id;
        }

        return $rules;
    }
}
