<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_id'     => $this->order_id,
            'product_name' => $this->product?->name,
            'qty'          => $this->quantity,
            'total'        => $this->quantity * $this->unit_price,
            'customer'     => $this->order?->customer?->name,
        ];
    }
}