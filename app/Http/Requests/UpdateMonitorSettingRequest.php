<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'in:1,5,10,15,30,60'],
            'latency_warning_ms' => ['required', 'integer', 'min:10', 'max:1000'],
            'dns_test_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'dns_test_domain' => strtolower(trim((string) $this->input('dns_test_domain'))),
        ]);
    }
}
