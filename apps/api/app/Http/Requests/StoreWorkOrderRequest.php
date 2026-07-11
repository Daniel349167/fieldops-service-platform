<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkOrderPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:40'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'array'],
            'address.line' => ['required', 'string', 'max:255'],
            'address.district' => ['nullable', 'string', 'max:120'],
            'address.city' => ['sometimes', 'string', 'max:120'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'priority' => ['sometimes', Rule::enum(WorkOrderPriority::class)],
            'assigned_technician_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::Technician->value)),
            ],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
