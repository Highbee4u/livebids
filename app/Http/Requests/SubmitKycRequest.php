<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nin' => ['required', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'selfie_base64' => ['nullable', 'string'],
            'consent' => ['required', 'accepted'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'nin.regex' => 'The NIN must be an 11-digit numeric string.',
            'consent.accepted' => 'You must agree and give explicit consent for identity verification lookup.',
        ];
    }
}
