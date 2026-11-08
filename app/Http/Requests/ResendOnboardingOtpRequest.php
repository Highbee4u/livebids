<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendOnboardingOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'uuid', 'exists:onboarding_requests,identifier'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.exists' => 'The provided onboarding session identifier is invalid.',
        ];
    }
}
