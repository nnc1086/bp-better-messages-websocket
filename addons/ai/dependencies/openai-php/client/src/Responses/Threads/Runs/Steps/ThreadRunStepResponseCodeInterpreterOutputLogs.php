<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Threads\Runs\Steps;

use BetterMessages\OpenAI\Contracts\ResponseContract;
use BetterMessages\OpenAI\Responses\Concerns\ArrayAccessible;
use BetterMessages\OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{type: 'logs', logs: string}>
 */
final class ThreadRunStepResponseCodeInterpreterOutputLogs implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: 'logs', logs: string}>
     */
    use ArrayAccessible;

    use Fakeable;

    /**
     * @param  'logs'  $type
     */
    private function __construct(
        public string $type,
        public string $logs,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'logs', logs: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['logs'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'logs' => $this->logs,
        ];
    }
}
