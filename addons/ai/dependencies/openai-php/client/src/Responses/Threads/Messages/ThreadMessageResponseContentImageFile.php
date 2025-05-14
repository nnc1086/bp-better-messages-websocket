<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Threads\Messages;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{file_id: string, detail?: string}>
 */
final class ThreadMessageResponseContentImageFile implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{file_id: string, detail?: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    private function __construct(
        public string $fileId,
        public ?string $detail,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{file_id: string, detail?: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['file_id'],
            $attributes['detail'] ?? null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return array_filter([
            'file_id' => $this->fileId,
            'detail' => $this->detail,
        ], fn (?string $value): bool => $value !== null);
    }
}
