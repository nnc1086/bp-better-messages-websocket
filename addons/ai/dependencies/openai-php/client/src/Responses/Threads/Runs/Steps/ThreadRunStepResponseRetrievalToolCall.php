<?php
/**
 * @license MIT
 *
 * Modified by __root__ on 08-April-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Threads\Runs\Steps;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{id: string, type: string, retrieval: array<string, string>}>
 */
final class ThreadRunStepResponseRetrievalToolCall implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, retrieval: array<string, string>}>
     */
    use ArrayAccessible;

    use Fakeable;

    /**
     * @param  array<string, string>  $retrieval
     */
    private function __construct(
        public string $id,
        public string $type,
        public array $retrieval,
    ) {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: 'retrieval', retrieval: array<string, string>}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['retrieval'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'retrieval' => $this->retrieval,
        ];
    }
}
