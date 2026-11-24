<?php

namespace App\Http\Requests\Rules;

/** Règles du paramètre "language" : code ISO 639-1 (fr, en…) ou "other", comme Twitch. */
final class LanguageCode
{
    public const PATTERN = '/^([a-z]{2,3}(-[a-z]{2})?|other)$/';

    /** @return list<string> */
    public static function rules(): array
    {
        return ['sometimes', 'string', 'regex:'.self::PATTERN];
    }
}
