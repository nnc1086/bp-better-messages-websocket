<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ModelsContract;
use BetterMessages\OpenAI\Resources\Models;
use BetterMessages\OpenAI\Responses\Models\DeleteResponse;
use BetterMessages\OpenAI\Responses\Models\ListResponse;
use BetterMessages\OpenAI\Responses\Models\RetrieveResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ModelsTestResource implements ModelsContract
{
    use Testable;

    protected function resource(): string
    {
        return Models::class;
    }

    public function list(): ListResponse
    {
        return $this->record(__FUNCTION__);
    }

    public function retrieve(string $model): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $model): DeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
