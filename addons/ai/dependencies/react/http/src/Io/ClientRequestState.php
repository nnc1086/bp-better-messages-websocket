<?php

namespace BetterMessages\React\Http\Io;

/** @internal */
class ClientRequestState
{
    /** @var int */
    public $numRequests = 0;

    /** @var ?\BetterMessages\React\Promise\PromiseInterface */
    public $pending = null;

    /** @var ?\BetterMessages\React\EventLoop\TimerInterface */
    public $timeout = null;
}
