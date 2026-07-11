<?php

namespace App\Http\Requests;

use App\Enums\WorkOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
