<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/warehouses")]
class WarehouseController extends BaseController
{
    protected string $modelClass = Warehouse::class;

    protected array $searchableFields = ['name', 'location'];

    protected array $withRelations = ['branch'];

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'branch_id' => 'required|uuid|exists:branches,id',
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
