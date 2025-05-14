<?php

namespace BetterMessages\React\Http\Client;

use BetterMessages\Psr\Http\Message\RequestInterface;
use BetterMessages\React\Http\Io\ClientConnectionManager;
use BetterMessages\React\Http\Io\ClientRequestStream;

/**
 * @internal
 */
class Client
{
    /** @var ClientConnectionManager */
    private $connectionManager;

    public function __construct(ClientConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    /** @return ClientRequestStream */
    public function request(RequestInterface $request)
    {
        return new ClientRequestStream($this->connectionManager, $request);
    }
}
