<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helixir:api-key:create {name : Who or what the key is for}')]
#[Description('Create an API key and print it once')]
class CreateApiKey extends Command
{
    public function handle(): int
    {
        [$apiKey, $plainKey] = ApiKey::generate($this->argument('name'));

        $this->info("API key #{$apiKey->id} created for \"{$apiKey->name}\".");
        $this->line($plainKey);
        $this->warn('Store it now: it cannot be displayed again.');

        return self::SUCCESS;
    }
}
