<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Transporters;

use Closure;
use BetterMessages\GuzzleHttp\Exception\ClientException;
use JsonException;
use BetterMessages\OpenAI\Contracts\TransporterContract;
use BetterMessages\OpenAI\Enums\Transporter\ContentType;
use BetterMessages\OpenAI\Exceptions\ErrorException;
use BetterMessages\OpenAI\Exceptions\TransporterException;
use BetterMessages\OpenAI\Exceptions\UnserializableResponse;
use BetterMessages\OpenAI\ValueObjects\Transporter\BaseUri;
use BetterMessages\OpenAI\ValueObjects\Transporter\Headers;
use BetterMessages\OpenAI\ValueObjects\Transporter\Payload;
use BetterMessages\OpenAI\ValueObjects\Transporter\QueryParams;
use BetterMessages\OpenAI\ValueObjects\Transporter\Response;
use BetterMessages\Psr\Http\Client\ClientExceptionInterface;
use BetterMessages\Psr\Http\Client\ClientInterface;
use BetterMessages\Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class HttpTransporter implements TransporterContract
{
    /**
     * Creates a new Http Transporter instance.
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly BaseUri $baseUri,
        private readonly Headers $headers,
        private readonly QueryParams $queryParams,
        private readonly Closure $streamHandler,
    ) {
        // ..
    }

    /**
     * {@inheritDoc}
     */
    public function requestObject(Payload $payload): Response
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn (): \BetterMessages\Psr\Http\Message\ResponseInterface => $this->client->sendRequest($request));

        $contents = $response->getBody()->getContents();

        if (str_contains($response->getHeaderLine('Content-Type'), ContentType::TEXT_PLAIN->value)) {
            return Response::from($contents, $response->getHeaders());
        }

        $this->throwIfJsonError($response, $contents);

        try {
            /** @var array{error?: array{message: string, type: string, code: string}} $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new UnserializableResponse($jsonException);
        }

        return Response::from($data, $response->getHeaders());
    }

    /**
     * {@inheritDoc}
     */
    public function requestContent(Payload $payload): string
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn (): \BetterMessages\Psr\Http\Message\ResponseInterface => $this->client->sendRequest($request));

        $contents = $response->getBody()->getContents();

        $this->throwIfJsonError($response, $contents);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function requestStream(Payload $payload): ResponseInterface
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->sendRequest(fn () => ($this->streamHandler)($request));

        $this->throwIfJsonError($response, $response);

        return $response;
    }

    private function sendRequest(Closure $callable): ResponseInterface
    {
        try {
            return $callable();
        } catch (ClientExceptionInterface $clientException) {
            if ($clientException instanceof ClientException) {
                $this->throwIfJsonError($clientException->getResponse(), $clientException->getResponse()->getBody()->getContents());
            }

            throw new TransporterException($clientException);
        }
    }

    private function throwIfJsonError(ResponseInterface $response, string|ResponseInterface $contents): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        if (! str_contains($response->getHeaderLine('Content-Type'), ContentType::JSON->value)) {
            return;
        }

        $statusCode = $response->getStatusCode();

        if ($contents instanceof ResponseInterface) {
            $contents = $contents->getBody()->getContents();
        }

        try {
            /** @var array{error?: array{message: string|array<int, string>, type: string, code: string}} $response */
            $response = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                throw new ErrorException($response['error'], $statusCode);
            }
        } catch (JsonException $jsonException) {
            throw new UnserializableResponse($jsonException);
        }
    }
}
