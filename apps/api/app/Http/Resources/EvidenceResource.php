<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'uploaded_by' => $this->uploaded_by,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'storage_path' => $this->storage_path,
            'checksum' => $this->checksum,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'metadata' => $this->metadata ?? (object) [],
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
