<?php

namespace Usamamuneerchaudhary\Notifier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'channels' => ['required', 'array'],
            'channels.*' => ['boolean'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'channels.required' => 'The channels field is required.',
            'channels.array' => 'The channels must be an array.',
            'channels.*.boolean' => 'Each channel value must be a boolean (true/false).',
            'settings.array' => 'The settings must be an array.',
        ];
    }
}
