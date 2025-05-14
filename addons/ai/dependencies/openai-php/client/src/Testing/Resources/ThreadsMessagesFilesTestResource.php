<?php
/**
 * @license MIT
 *
 * Modified by __root__ on 08-April-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\ThreadsMessagesFilesContract;
use BetterMessages\OpenAI\Resources\ThreadsMessagesFiles;
use BetterMessages\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileListResponse;
use BetterMessages\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class ThreadsMessagesFilesTestResource implements ThreadsMessagesFilesContract
{
    use Testable;

    public function resource(): string
    {
        return ThreadsMessagesFiles::class;
    }

    public function retrieve(string $threadId, string $messageId, string $fileId): ThreadMessageFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $threadId, string $messageId, array $parameters = []): ThreadMessageFileListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
