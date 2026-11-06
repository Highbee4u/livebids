<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Terms\Enums\TermsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(TermsType::values())],
            'version' => [
                'required',
                'string',
                'max:20',
                Rule::unique('terms_and_conditions')->where(function ($query) {
                    return $query->where('type', $this->input('type'));
                }),
            ],
            'content' => ['required', 'string'],
            'summary_of_changes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Invalid terms type. Must be one of: '.implode(', ', TermsType::values()),
            'version.unique' => 'A terms document with this type and version already exists.',
        ];
    }
}
