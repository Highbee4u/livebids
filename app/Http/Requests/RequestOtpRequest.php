<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'purpose' => ['nullable', 'string', 'in:login,withdrawal,pin_reset'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
