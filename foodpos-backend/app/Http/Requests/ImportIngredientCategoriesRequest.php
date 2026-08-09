<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportIngredientCategoriesRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV or Excel file to import.',
            'file.mimes' => 'The file must be a CSV or Excel (.xlsx, .xls) file.',
            'file.max' => 'The file may not be larger than 5 MB.',
        ];
    }
}
