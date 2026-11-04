<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOnboardingOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'uuid', 'exists:onboarding_requests,identifier'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
