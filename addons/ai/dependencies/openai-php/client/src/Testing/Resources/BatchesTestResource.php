<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\BatchesContract;
use BetterMessages\OpenAI\Resources\Batches;
use BetterMessages\OpenAI\Responses\Batches\BatchListResponse;
use BetterMessages\OpenAI\Responses\Batches\BatchResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class BatchesTestResource implements BatchesContract
{
    use Testable;

    public function resource(): string
    {
        return Batches::class;
    }

    public function create(array $parameters): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancel(string $id): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): BatchListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
