<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Responses\Concerns;

use BetterMessages\OpenAI\Responses\Meta\MetaInformation;

trait HasMetaInformation
{
    public function meta(): MetaInformation
    {
        return $this->meta;
    }
}
