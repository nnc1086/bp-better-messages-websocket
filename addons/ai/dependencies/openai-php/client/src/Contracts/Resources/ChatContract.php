<?php

namespace BetterMessages\OpenAI\Contracts\Resources;

use BetterMessages\OpenAI\Responses\Chat\CreateResponse;
use BetterMessages\OpenAI\Responses\Chat\CreateStreamedResponse;
use BetterMessages\OpenAI\Responses\StreamResponse;

interface ChatContract
{
    /**
     * Creates a completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse;

    /**
     * Creates a streamed completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse;
}
