<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'is_low_stock' => $this->isLowStock(),
            'is_active' => $this->is_active,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'category' => CategoryResource::make($this->whenLoaded('category')),
        ];
    }
}
