<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Threads\Messages;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{type: 'file_citation', text: string, file_citation: array{file_id: string, quote?: string}, start_index: int, end_index: int}>
 */
final class ThreadMessageResponseContentTextAnnotationFileCitationObject implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: 'file_citation', text: string, file_citation: array{file_id: string, quote?: string}, start_index: int, end_index: int}>
     */
    use ArrayAccessible;

    use Fakeable;

    /**
     * @param  'file_citation'  $type
     */
    private function __construct(
        public string $type,
        public string $text,
        public int $startIndex,
        public int $endIndex,
        public ThreadMessageResponseContentTextAnnotationFileCitation $fileCitation,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'file_citation', text: string, file_citation: array{file_id: string, quote?: string}, start_index: int, end_index: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['text'],
            $attributes['start_index'],
            $attributes['end_index'],
            ThreadMessageResponseContentTextAnnotationFileCitation::from($attributes['file_citation']),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'start_index' => $this->startIndex,
            'end_index' => $this->endIndex,
            'file_citation' => $this->fileCitation->toArray(),
        ];
    }
}
