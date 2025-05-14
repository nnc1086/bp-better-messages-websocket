<?php

declare(strict_types=1);

namespace BetterMessages\OpenAI\Contracts;

use BetterMessages\OpenAI\Responses\Meta\MetaInformation;

interface ResponseHasMetaInformationContract
{
    public function meta(): MetaInformation;
}
