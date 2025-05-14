<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BetterMessages\Symfony\Component\HttpClient;

use BetterMessages\Symfony\Component\HttpClient\Response\AsyncResponse;
use BetterMessages\Symfony\Component\HttpClient\Response\ResponseStream;
use BetterMessages\Symfony\Contracts\HttpClient\ResponseInterface;
use BetterMessages\Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Eases with processing responses while streaming them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait AsyncDecoratorTrait
{
    use DecoratorTrait;

    /**
     * @return AsyncResponse
     */
    abstract public function request(string $method, string $url, array $options = []): ResponseInterface;

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof AsyncResponse) {
            $responses = [$responses];
        }

        return new ResponseStream(AsyncResponse::stream($responses, $timeout, static::class));
    }
}
