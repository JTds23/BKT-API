<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'service_provider' => new ServiceProviderResource($this->whenLoaded('serviceProvider')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'quote' => new QuoteResource($this->whenLoaded('quote')),
        ];
    }
}
