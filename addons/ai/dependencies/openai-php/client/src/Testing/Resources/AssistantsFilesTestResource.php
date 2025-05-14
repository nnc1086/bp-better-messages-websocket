<?php
/**
 * @license MIT
 *
 * Modified by __root__ on 08-April-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\AssistantsFilesContract;
use BetterMessages\OpenAI\Resources\AssistantsFiles;
use BetterMessages\OpenAI\Responses\Assistants\Files\AssistantFileDeleteResponse;
use BetterMessages\OpenAI\Responses\Assistants\Files\AssistantFileListResponse;
use BetterMessages\OpenAI\Responses\Assistants\Files\AssistantFileResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class AssistantsFilesTestResource implements AssistantsFilesContract
{
    use Testable;

    public function resource(): string
    {
        return AssistantsFiles::class;
    }

    public function create(string $assistantId, array $parameters): AssistantFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $assistantId, string $fileId): AssistantFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $assistantId, string $fileId): AssistantFileDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $assistantId, array $parameters = []): AssistantFileListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
