<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_kobo' => ['required', 'integer', 'min:100000', 'max:1000000000'], // ₦1,000 min to ₦10,000,000 max
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'pin' => ['nullable', 'string', 'min:4', 'max:6'],
        ];
    }
}
