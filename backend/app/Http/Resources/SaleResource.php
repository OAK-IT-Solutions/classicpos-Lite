<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasItems = $this->relationLoaded('items');

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'efris_fdn' => $this->efris_fdn,
            'efris_qr_code' => $this->efris_qr_code,
            'efris_verification_code' => $this->efris_verification_code,
            'efris_fiscal_status' => $this->efris_fiscal_status,
            'created_at' => $this->created_at->toISOString(),
            ...$hasItems ? [
                'subtotal' => (float) $this->items->sum('subtotal'),
                'discount' => (float) $this->discount,
                'tax_amount' => (float) $this->tax_amount,
                'items' => $this->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? 'Unknown Product',
                    'qty' => (float) $item->quantity,
                    'price' => (float) $item->price,
                ]),
            ] : [],
        ];
    }
}
