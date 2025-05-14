<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ThreadsContract;
use BetterMessages\OpenAI\Resources\Threads;
use BetterMessages\OpenAI\Responses\StreamResponse;
use BetterMessages\OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use BetterMessages\OpenAI\Responses\Threads\ThreadDeleteResponse;
use BetterMessages\OpenAI\Responses\Threads\ThreadResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ThreadsTestResource implements ThreadsContract
{
    use Testable;

    public function resource(): string
    {
        return Threads::class;
    }

    public function create(array $parameters): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createAndRun(array $parameters): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createAndRunStreamed(array $parameters): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $id, array $parameters): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): ThreadDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function messages(): ThreadsMessagesTestResource
    {
        return new ThreadsMessagesTestResource($this->fake);
    }

    public function runs(): ThreadsRunsTestResource
    {
        return new ThreadsRunsTestResource($this->fake);
    }
}
