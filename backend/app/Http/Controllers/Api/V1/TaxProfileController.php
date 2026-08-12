<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TaxProfile;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/tax-profiles")]
class TaxProfileController extends BaseController
{
    protected string $modelClass = TaxProfile::class;

    protected array $searchableFields = ['name', 'description'];

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:inclusive,exclusive',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
