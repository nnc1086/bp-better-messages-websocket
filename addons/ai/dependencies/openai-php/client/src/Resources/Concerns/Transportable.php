<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Resources\Concerns;

use BetterMessages\OpenAI\Contracts\TransporterContract;

trait Transportable
{
    public function __construct(private readonly TransporterContract $transporter)
    {
        // ..
    }
}
