<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ChatContract;
use BetterMessages\OpenAI\Resources\Chat;
use BetterMessages\OpenAI\Responses\Chat\CreateResponse;
use BetterMessages\OpenAI\Responses\StreamResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ChatTestResource implements ChatContract
{
    use Testable;

    protected function resource(): string
    {
        return Chat::class;
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
