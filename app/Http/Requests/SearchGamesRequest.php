<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Paramètres de GET /api/v1/games/search. */
class SearchGamesRequest extends FormRequest
{
    public const MAX_RESULTS = 20;

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function term(): string
    {
        return trim($this->validated('q'));
    }
}
