<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Promotion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/promotions")]
class PromotionController extends BaseController
{
    protected string $modelClass = Promotion::class;

    protected array $searchableFields = ['code', 'description'];

    protected function rules(Request $request, ?string $id = null): array
    {
        $unique = $id ? 'unique:promotions,code,' . $id : 'unique:promotions,code';

        return [
            'code' => 'required|string|max:50|' . $unique,
            'type' => 'required|in:percentage,flat',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
