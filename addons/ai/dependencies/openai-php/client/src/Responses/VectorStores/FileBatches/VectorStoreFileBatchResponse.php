<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\VectorStores\FileBatches;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Responses\Concerns\HasMetaInformation;
use BetterMessages\OpenAI\Responses\Meta\MetaInformation;
use BetterMessages\OpenAI\Responses\VectorStores\VectorStoreResponseFileCounts;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{id: string, object: string, created_at: int, vector_store_id: string, status: string, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}}>
 */
final class VectorStoreFileBatchResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, object: string, created_at: int, vector_store_id: string, status: string, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    private function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $createdAt,
        public readonly string $vectorStoreId,
        public readonly string $status,
        public readonly VectorStoreResponseFileCounts $fileCounts,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, object: string, created_at: int, vector_store_id: string, status: string, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['object'],
            $attributes['created_at'],
            $attributes['vector_store_id'],
            $attributes['status'],
            VectorStoreResponseFileCounts::from($attributes['file_counts']),
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created_at' => $this->createdAt,
            'vector_store_id' => $this->vectorStoreId,
            'status' => $this->status,
            'file_counts' => $this->fileCounts->toArray(),
        ];
    }
}
