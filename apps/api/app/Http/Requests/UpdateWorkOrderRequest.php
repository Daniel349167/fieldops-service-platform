<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkOrderPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'customer' => ['sometimes', 'array'],
            'customer.name' => ['sometimes', 'string', 'max:255'],
            'customer.phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'customer.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'array'],
            'address.line' => ['sometimes', 'string', 'max:255'],
            'address.district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address.city' => ['sometimes', 'string', 'max:120'],
            'address.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'priority' => ['sometimes', Rule::enum(WorkOrderPriority::class)],
            'assigned_technician_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::Technician->value)),
            ],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
