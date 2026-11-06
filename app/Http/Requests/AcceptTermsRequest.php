<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'terms_id' => ['required', 'integer', 'exists:terms_and_conditions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_id.required' => 'The terms ID is required.',
            'terms_id.exists' => 'The selected terms and conditions version was not found.',
        ];
    }
}
