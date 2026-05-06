<?php

namespace App\Http\Requests\Subcontractor;

use App\ActivityFields\ConstructionFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConstructionDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ConstructionFields::validationRules();
    }
}
