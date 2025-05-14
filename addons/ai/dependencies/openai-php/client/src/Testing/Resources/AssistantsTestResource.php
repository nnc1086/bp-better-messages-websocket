<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\AssistantsContract;
use BetterMessages\OpenAI\Resources\Assistants;
use BetterMessages\OpenAI\Responses\Assistants\AssistantDeleteResponse;
use BetterMessages\OpenAI\Responses\Assistants\AssistantListResponse;
use BetterMessages\OpenAI\Responses\Assistants\AssistantResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class AssistantsTestResource implements AssistantsContract
{
    use Testable;

    public function resource(): string
    {
        return Assistants::class;
    }

    public function create(array $parameters): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $id, array $parameters): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): AssistantDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): AssistantListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
