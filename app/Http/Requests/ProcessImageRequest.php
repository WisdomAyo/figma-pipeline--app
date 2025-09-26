<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for validating image processing API requests
 *
 * Ensures that all required parameters are present and valid
 * before initiating the image processing pipeline
 */
class ProcessImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_key' => 'required|string|min:1|max:255',
            'node_id'  => 'required|string|min:1|max:255'
        ];
    }

    /**
     * Get custom error messages for validation failures
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_key.required' => __('The Figma file key is required.'),
            'file_key.string'   => __('The Figma file key must be a valid string.'),
            'file_key.min'      => __('The Figma file key cannot be empty.'),
            'file_key.max'      => __('The Figma file key is too long.'),
            'node_id.required'  => __('The node ID is required.'),
            'node_id.string'    => __('The node ID must be a valid string.'),
            'node_id.min'       => __('The node ID cannot be empty.'),
            'node_id.max'       => __('The node ID is too long.')
        ];
    }
}
