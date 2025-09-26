<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTailwindConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        // allow access - adjust to policies as required
        return true;
    }

    public function rules(): array
    {
         return [
        'file_key'       => ['required_without:variables_json', 'string'],
        'variables_json' => ['required_without:file_key', 'array'],
        'format'         => ['nullable', 'in:js,json'],
    ];
    }

    public function messages(): array
    {
        return [
            'file_key.required' => 'Figma file key is required.',
        ];
    }
}
