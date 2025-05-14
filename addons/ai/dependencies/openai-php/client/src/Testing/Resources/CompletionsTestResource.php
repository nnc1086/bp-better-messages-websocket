<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\CompletionsContract;
use BetterMessages\OpenAI\Resources\Completions;
use BetterMessages\OpenAI\Responses\Completions\CreateResponse;
use BetterMessages\OpenAI\Responses\StreamResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class CompletionsTestResource implements CompletionsContract
{
    use Testable;

    protected function resource(): string
    {
        return Completions::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createStreamed(array $parameters): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
