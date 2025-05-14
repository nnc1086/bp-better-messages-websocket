<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Threads\Messages;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{type: 'code_interpreter'}>
 */
final class ThreadMessageResponseAttachmentCodeInterpreterTool implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: 'code_interpreter'}>
     */
    use ArrayAccessible;

    use Fakeable;

    /**
     * @param  'code_interpreter'  $type
     */
    private function __construct(
        public string $type,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'code_interpreter'}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
