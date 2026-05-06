<?php

namespace App\Http\Requests\Subcontractor;

use App\ActivityFields\BastFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBastDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(BastFields::validationRules(), [
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
