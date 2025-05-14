<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\FilesContract;
use BetterMessages\OpenAI\Resources\Files;
use BetterMessages\OpenAI\Responses\Files\CreateResponse;
use BetterMessages\OpenAI\Responses\Files\DeleteResponse;
use BetterMessages\OpenAI\Responses\Files\ListResponse;
use BetterMessages\OpenAI\Responses\Files\RetrieveResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class FilesTestResource implements FilesContract
{
    use Testable;

    protected function resource(): string
    {
        return Files::class;
    }

    public function list(): ListResponse
    {
        return $this->record(__FUNCTION__);
    }

    public function retrieve(string $file): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function download(string $file): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function upload(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $file): DeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
