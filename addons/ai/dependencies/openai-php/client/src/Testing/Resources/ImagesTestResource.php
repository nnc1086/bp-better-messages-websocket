<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ImagesContract;
use BetterMessages\OpenAI\Resources\Images;
use BetterMessages\OpenAI\Responses\Images\CreateResponse;
use BetterMessages\OpenAI\Responses\Images\EditResponse;
use BetterMessages\OpenAI\Responses\Images\VariationResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ImagesTestResource implements ImagesContract
{
    use Testable;

    protected function resource(): string
    {
        return Images::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function edit(array $parameters): EditResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function variation(array $parameters): VariationResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
