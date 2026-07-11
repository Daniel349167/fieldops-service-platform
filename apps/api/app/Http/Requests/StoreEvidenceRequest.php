<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', Rule::in(['image/jpeg', 'image/png', 'image/webp', 'image/heic'])],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:20971520'],
            'storage_path' => ['required', 'string', 'max:500'],
            'checksum' => ['nullable', 'string', 'max:128'],
            'captured_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'metadata.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'metadata.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'metadata.width' => ['nullable', 'integer', 'min:1'],
            'metadata.height' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
