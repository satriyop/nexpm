<?php

namespace App\Http\Requests\Subcontractor;

use App\ActivityFields\SurveyFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSurveyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return SurveyFields::validationRules();
    }
}
