<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class OrderItemResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'variants' => $this->variants,
            'special_instructions' => $this->special_instructions,
            'status' => $this->status,
            'menu_item' => $this->whenLoaded('menuItem', function () {
                return [
                    'id' => $this->menuItem->id,
                    'name' => $this->menuItem->name,
                    'image' => $this->menuItem->resolvedImageUrl(),
                ];
            }),
        ];
    }
}
