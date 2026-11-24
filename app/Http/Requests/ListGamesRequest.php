<?php

namespace App\Http\Requests;

use App\Http\Requests\Rules\LanguageCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide les paramètres de GET /api/v1/games.
 * Si la validation échoue, Laravel répond 422 avant d'entrer dans le contrôleur.
 */
class ListGamesRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    /** Valeur du paramètre "sort" => colonne SQL. */
    public const SORTS = [
        'viewers' => 'viewers_count',
        'streams' => 'streams_count',
    ];

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', Rule::in(array_keys(self::SORTS))],
            'language' => LanguageCode::rules(),
        ];
    }

    public function limit(): int
    {
        return $this->integer('limit', self::DEFAULT_LIMIT);
    }

    public function sortColumn(): string
    {
        return self::SORTS[$this->string('sort', 'viewers')->toString()];
    }

    public function language(): ?string
    {
        return $this->validated('language');
    }
}
