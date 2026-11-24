<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Paramètres de GET /api/v1/games/{id}/top-streamers. */
class TopStreamersRequest extends FormRequest
{
    public const LIMITS = [5, 10];

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', Rule::in(self::LIMITS)],
            // Seules les langues suivies par le collecteur ont un top complet.
            'language' => ['sometimes', 'string', Rule::in(config('helixir.collector.tracked_languages'))],
        ];
    }

    public function messages(): array
    {
        return [
            'language.in' => 'Top streamers are only tracked for: '.implode(', ', config('helixir.collector.tracked_languages')).'.',
        ];
    }

    public function limit(): int
    {
        return $this->integer('limit', max(self::LIMITS));
    }

    public function language(): ?string
    {
        return $this->validated('language');
    }
}
