<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'code' => ['required', 'string', 'digits:6'],
        ];

        // If not authenticated via Bearer token, require identifier or email
        if (! $this->user()) {
            $rules['identifier'] = ['required_without:email', 'nullable', 'string', 'uuid', 'exists:users,identifier'];
            $rules['email'] = ['required_without:identifier', 'nullable', 'string', 'email', 'exists:users,email'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'The 6-digit verification code is required.',
            'code.digits' => 'The verification code must be exactly 6 digits.',
            'identifier.exists' => 'No user found with the provided identifier.',
            'email.exists' => 'No user found with the provided email address.',
        ];
    }
}
