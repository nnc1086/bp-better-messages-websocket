<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\EditsContract;
use BetterMessages\OpenAI\Resources\Edits;
use BetterMessages\OpenAI\Responses\Edits\CreateResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class EditsTestResource implements EditsContract
{
    use Testable;

    protected function resource(): string
    {
        return Edits::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
