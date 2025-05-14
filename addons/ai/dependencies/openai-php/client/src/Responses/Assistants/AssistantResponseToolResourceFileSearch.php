<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Assistants;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{vector_store_ids: array<int,string>}>
 */
final class AssistantResponseToolResourceFileSearch implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{vector_store_ids: array<int,string>}>
     */
    use ArrayAccessible;

    use Fakeable;

    /**
     * @param  array<int, string>  $vectorStoreIds
     */
    private function __construct(
        public array $vectorStoreIds,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{vector_store_ids: array<int,string>}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['vector_store_ids'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'vector_store_ids' => $this->vectorStoreIds,
        ];
    }
}
