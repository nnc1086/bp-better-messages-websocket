<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\VectorStoresContract;
use BetterMessages\OpenAI\Contracts\Resources\VectorStoresFileBatchesContract;
use BetterMessages\OpenAI\Contracts\Resources\VectorStoresFilesContract;
use BetterMessages\OpenAI\Resources\VectorStores;
use BetterMessages\OpenAI\Responses\VectorStores\VectorStoreDeleteResponse;
use BetterMessages\OpenAI\Responses\VectorStores\VectorStoreListResponse;
use BetterMessages\OpenAI\Responses\VectorStores\VectorStoreResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class VectorStoresTestResource implements VectorStoresContract
{
    use Testable;

    public function resource(): string
    {
        return VectorStores::class;
    }

    public function modify(string $vectorStoreId, array $parameters): VectorStoreResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $vectorStoreId): VectorStoreResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $vectorStoreId): VectorStoreDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function create(array $parameters): VectorStoreResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): VectorStoreListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function files(): VectorStoresFilesContract
    {
        return new VectorStoresFilesTestResource($this->fake);
    }

    public function batches(): VectorStoresFileBatchesContract
    {
        return new VectorStoresFileBatchesTestResource($this->fake);
    }
}
