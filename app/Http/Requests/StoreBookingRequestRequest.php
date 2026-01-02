<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_provider_id' => [
                'required',
                'integer',
                Rule::exists('service_providers', 'id'),
            ],
            'task_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'task_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tasks', 'id')->where(function ($query) {
                    $query->where('service_provider_id', $this->input('service_provider_id'));
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'service_provider_id.required' => 'A service provider must be selected.',
            'service_provider_id.exists' => 'The selected service provider does not exist.',
            'task_ids.required' => 'At least one task must be selected.',
            'task_ids.min' => 'At least one task must be selected.',
            'task_ids.*.exists' => 'One or more selected tasks are invalid or do not belong to the selected service provider.',
            'task_ids.*.distinct' => 'Duplicate tasks are not allowed in the same booking request.',
        ];
    }
}
