<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ModerationsContract;
use BetterMessages\OpenAI\Resources\Moderations;
use BetterMessages\OpenAI\Responses\Moderations\CreateResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ModerationsTestResource implements ModerationsContract
{
    use Testable;

    protected function resource(): string
    {
        return Moderations::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
