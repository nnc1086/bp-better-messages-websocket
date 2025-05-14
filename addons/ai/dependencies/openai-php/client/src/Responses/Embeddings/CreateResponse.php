<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Embeddings;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Responses\Concerns\HasMetaInformation;
use BetterMessages\OpenAI\Responses\Meta\MetaInformation;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{object: string, data: array<int, array{object: string, embedding: array<int, float>, index: int}>, usage: array{prompt_tokens: int, total_tokens: int}}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{object: string, data: array<int, array{object: string, embedding: array<int, float>, index: int}>, usage: array{prompt_tokens: int, total_tokens: int}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, CreateResponseEmbedding>  $embeddings
     */
    private function __construct(
        public readonly string $object,
        public readonly array $embeddings,
        public readonly CreateResponseUsage $usage,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{object: string, data: array<int, array{object: string, embedding: array<int, float>, index: int}>, usage: array{prompt_tokens: int, total_tokens: int}}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $embeddings = array_map(fn (array $result): CreateResponseEmbedding => CreateResponseEmbedding::from(
            $result
        ), $attributes['data']);

        return new self(
            $attributes['object'],
            $embeddings,
            CreateResponseUsage::from($attributes['usage']),
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'object' => $this->object,
            'data' => array_map(
                static fn (CreateResponseEmbedding $result): array => $result->toArray(),
                $this->embeddings,
            ),
            'usage' => $this->usage->toArray(),
        ];
    }
}
