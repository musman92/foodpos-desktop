<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;

class MenuItemResource extends BaseResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->resolvedImageUrl(),
            'price' => (float) $this->price,
            'is_available' => $this->is_available,
            'preparation_time' => $this->preparation_time,
            'variants' => $this->variants,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
        ];
    }
}
