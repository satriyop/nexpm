<?php

namespace App\Http\Requests\Subcontractor;

use App\ActivityFields\PlnFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlnDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PlnFields::validationRules();
    }
}
