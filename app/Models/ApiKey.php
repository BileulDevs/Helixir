<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    private const PREFIX = 'hx_';

    private const RANDOM_LENGTH = 40;

    /** On n'enregistre pas last_used_at à chaque requête, au plus une fois par minute. */
    private const USAGE_WRITE_INTERVAL_SECONDS = 60;

    protected $fillable = ['name', 'key_hash'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Crée une clé et renvoie sa valeur en clair, qui ne pourra plus être relue ensuite.
     *
     * @return array{0: self, 1: string}
     */
    public static function generate(string $name): array
    {
        $plainKey = self::PREFIX.Str::random(self::RANDOM_LENGTH);

        $apiKey = self::create(['name' => $name, 'key_hash' => self::hash($plainKey)]);

        return [$apiKey, $plainKey];
    }

    /** La clé active correspondant à une valeur en clair, ou null. */
    public static function findActive(string $plainKey): ?self
    {
        return self::query()
            ->where('key_hash', self::hash($plainKey))
            ->whereNull('revoked_at')
            ->first();
    }

    public function markAsUsed(): void
    {
        if ($this->last_used_at?->greaterThan(now()->subSeconds(self::USAGE_WRITE_INTERVAL_SECONDS))) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Les clés sont longues et aléatoires : un SHA-256 suffit (pas besoin de bcrypt
     * comme pour un mot de passe) et permet une recherche directe par index.
     */
    private static function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }
}
