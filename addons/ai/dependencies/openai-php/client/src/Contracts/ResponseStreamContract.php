<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Contracts;

use IteratorAggregate;

/**
 * @template T
 *
 * @extends IteratorAggregate<int, T>
 *
 * @internal
 */
interface ResponseStreamContract extends IteratorAggregate {}
