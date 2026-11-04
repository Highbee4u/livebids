<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Identity\Services\LocationService;
use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'uuid', 'exists:onboarding_requests,identifier'],
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:-18 years'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'lga_id' => ['required', 'integer', 'exists:lgas,id'],
            'street_address' => ['required', 'string', 'min:5', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $stateId = (int) $this->input('state_id');
            $lgaId = (int) $this->input('lga_id');

            if ($stateId && $lgaId) {
                $locationService = app(LocationService::class);
                if (! $locationService->lgaBelongsToState($lgaId, $stateId)) {
                    $validator->errors()->add('lga_id', 'The selected LGA does not belong to the selected State.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old to use LiveBids.',
            'country_id.exists' => 'The selected country is not supported.',
            'state_id.exists' => 'The selected state is invalid.',
            'lga_id.exists' => 'The selected LGA is invalid.',
        ];
    }
}
