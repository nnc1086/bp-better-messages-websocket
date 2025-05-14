<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\EmbeddingsContract;
use BetterMessages\OpenAI\Resources\Embeddings;
use BetterMessages\OpenAI\Responses\Embeddings\CreateResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class EmbeddingsTestResource implements EmbeddingsContract
{
    use Testable;

    protected function resource(): string
    {
        return Embeddings::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
