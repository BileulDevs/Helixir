<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helixir:api-key:revoke {id : The API key id}')]
#[Description('Revoke an API key')]
class RevokeApiKey extends Command
{
    public function handle(): int
    {
        $apiKey = ApiKey::find($this->argument('id'));

        if ($apiKey === null) {
            $this->error('No API key with this id.');

            return self::FAILURE;
        }

        $apiKey->revoke();
        $this->info("API key #{$apiKey->id} (\"{$apiKey->name}\") revoked.");

        return self::SUCCESS;
    }
}
