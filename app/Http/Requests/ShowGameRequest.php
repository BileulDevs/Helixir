<?php

namespace App\Http\Requests;

use App\Http\Requests\Rules\LanguageCode;
use Illuminate\Foundation\Http\FormRequest;

/** Paramètres de GET /api/v1/games/{id}. */
class ShowGameRequest extends FormRequest
{
    public function rules(): array
    {
        return ['language' => LanguageCode::rules()];
    }

    public function language(): ?string
    {
        return $this->validated('language');
    }
}
