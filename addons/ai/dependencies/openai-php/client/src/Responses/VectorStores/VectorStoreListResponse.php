<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\VectorStores;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Responses\Concerns\HasMetaInformation;
use BetterMessages\OpenAI\Responses\Meta\MetaInformation;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{object: string, data: array<int, array{id: string, object: string, created_at: int, name: ?string, usage_bytes: int, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}, status: string, expires_after: ?array{anchor: string, days: int}, expires_at: ?int, last_active_at: ?int, metadata: array<string, string>}>, first_id: ?string, last_id: ?string, has_more: bool}>
 */
final class VectorStoreListResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{object: string, data: array<int, array{id: string, object: string, created_at: int, name: ?string, usage_bytes: int, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}, status: string, expires_after: ?array{anchor: string, days: int}, expires_at: ?int, last_active_at: ?int, metadata: array<string, string>}>, first_id: ?string, last_id: ?string, has_more: bool}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, VectorStoreResponse>  $data
     */
    private function __construct(
        public readonly string $object,
        public readonly array $data,
        public readonly ?string $firstId,
        public readonly ?string $lastId,
        public readonly bool $hasMore,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{object: string, data: array<int, array{id: string, object: string, created_at: int, name: ?string, usage_bytes: int, file_counts: array{in_progress: int, completed: int, failed: int, cancelled: int, total: int}, status: string, expires_after: ?array{anchor: string, days: int}, expires_at: ?int, last_active_at: ?int, metadata: array<string, string>}>, first_id: ?string, last_id: ?string, has_more: bool}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $data = array_map(fn (array $result): VectorStoreResponse => VectorStoreResponse::from(
            $result,
            $meta,
        ), $attributes['data']);

        return new self(
            $attributes['object'],
            $data,
            $attributes['first_id'],
            $attributes['last_id'],
            $attributes['has_more'],
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array{object: string, data: array<int, mixed[]>, first_id: string|null, last_id: string|null, has_more: bool}
     */
    public function toArray(): array
    {
        return [
            'object' => $this->object,
            'data' => array_map(
                static fn (VectorStoreResponse $response): array => $response->toArray(),
                $this->data,
            ),
            'first_id' => $this->firstId,
            'last_id' => $this->lastId,
            'has_more' => $this->hasMore,
        ];
    }
}
