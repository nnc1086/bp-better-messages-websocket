<?php

declare(strict_types=1);

use BetterMessages\OpenAI\Client;
use BetterMessages\OpenAI\Factory;

final class BetterMessages_OpenAI
{
    /**
     * Creates a new Open AI Client with the given API token.
     */
    public static function client(string $apiKey, ?string $organization = null, ?string $project = null): Client
    {
        return self::factory()
            ->withApiKey($apiKey)
            ->withOrganization($organization)
            ->withProject($project)
            ->withHttpHeader('BetterMessages_OpenAI-Beta', 'assistants=v2')
            ->make();
    }

    /**
     * Creates a new factory instance to configure a custom Open AI Client
     */
    public static function factory(): Factory
    {
        return new Factory;
    }
}
