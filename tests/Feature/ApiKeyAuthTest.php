<?php

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects requests without a valid key', function (?string $key) {
    $request = $key === null ? $this : $this->withToken($key);

    $request->getJson('/api/v1/games')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
})->with([
    'no header' => null,
    'unknown key' => 'hx_not-a-real-key',
]);

it('rejects a revoked key', function () {
    [$apiKey, $plainKey] = ApiKey::generate('old client');
    $apiKey->revoke();

    $this->withToken($plainKey)->getJson('/api/v1/games')->assertUnauthorized();
});

it('accepts a valid key and records its last use', function () {
    [$apiKey, $plainKey] = ApiKey::generate('client');

    $this->withToken($plainKey)->getJson('/api/v1/games')->assertOk();

    expect($apiKey->fresh()->last_used_at)->not->toBeNull();
});

it('stores only a hash of the key', function () {
    [$apiKey, $plainKey] = ApiKey::generate('client');

    expect($plainKey)->toStartWith('hx_')
        ->and($apiKey->key_hash)->toBe(hash('sha256', $plainKey))
        ->and($apiKey->toArray())->not->toHaveKey('key_hash');
});

it('keeps the health endpoint public', function () {
    $this->getJson('/api/v1/health')->assertOk();
});

it('limits each key to its quota per minute', function () {
    config(['helixir.api.rate_limit_per_minute' => 2]);
    [, $plainKey] = ApiKey::generate('client');
    [, $otherKey] = ApiKey::generate('other client');

    $this->withToken($plainKey)->getJson('/api/v1/games')->assertOk();
    $this->withToken($plainKey)->getJson('/api/v1/games')->assertOk();

    $this->withToken($plainKey)->getJson('/api/v1/games')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited');

    // Le quota est propre à chaque clé.
    $this->withToken($otherKey)->getJson('/api/v1/games')->assertOk();
});

it('creates and revokes keys from the command line', function () {
    $this->artisan('helixir:api-key:create', ['name' => 'cli'])
        ->expectsOutputToContain('cannot be displayed again')
        ->assertSuccessful();

    $apiKey = ApiKey::where('name', 'cli')->sole();

    $this->artisan('helixir:api-key:revoke', ['id' => $apiKey->id])->assertSuccessful();
    expect($apiKey->fresh()->revoked_at)->not->toBeNull();

    $this->artisan('helixir:api-key:revoke', ['id' => 999])->assertFailed();
});
