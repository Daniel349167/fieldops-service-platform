<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'customer' => ['name' => $this->customer_name, 'phone' => $this->customer_phone, 'email' => $this->customer_email],
            'address' => [
                'line' => $this->address_line, 'district' => $this->district, 'city' => $this->city,
                'latitude' => $this->latitude === null ? null : (float) $this->latitude,
                'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            ],
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'assigned_technician' => $this->whenLoaded('assignedTechnician', fn () => $this->assignedTechnician ? new UserResource($this->assignedTechnician) : null),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
