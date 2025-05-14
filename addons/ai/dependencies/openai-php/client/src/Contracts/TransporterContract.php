<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Contracts;

use BetterMessages\OpenAI\Exceptions\ErrorException;
use BetterMessages\OpenAI\Exceptions\TransporterException;
use BetterMessages\OpenAI\Exceptions\UnserializableResponse;
use BetterMessages\OpenAI\ValueObjects\Transporter\Payload;
use BetterMessages\OpenAI\ValueObjects\Transporter\Response;
use BetterMessages\Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
interface TransporterContract
{
    /**
     * Sends a request to a server.
     *
     * @return Response<array<array-key, mixed>|string>
     *
     * @throws ErrorException|UnserializableResponse|TransporterException
     */
    public function requestObject(Payload $payload): Response;

    /**
     * Sends a content request to a server.
     *
     * @throws ErrorException|TransporterException
     */
    public function requestContent(Payload $payload): string;

    /**
     * Sends a stream request to a server.
     **
     * @throws ErrorException
     */
    public function requestStream(Payload $payload): ResponseInterface;
}
