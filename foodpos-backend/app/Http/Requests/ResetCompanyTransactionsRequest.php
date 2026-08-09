<?php

namespace App\Http\Requests;

use App\Support\TenantTransactionalResetOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResetCompanyTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_reset' => ['required', 'in:RESET'],
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['string', Rule::in(TenantTransactionalResetOptions::allKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_reset.required' => 'Type RESET to confirm this operation.',
            'confirm_reset.in' => 'Confirmation text must be RESET.',
            'options.required' => 'Select at least one reset option.',
            'options.min' => 'Select at least one reset option.',
        ];
    }

    /**
     * @return list<string>
     */
    public function normalizedOptions(): array
    {
        /** @var list<string> $options */
        $options = $this->input('options', []);

        return TenantTransactionalResetOptions::normalizeSelection($options);
    }
}
