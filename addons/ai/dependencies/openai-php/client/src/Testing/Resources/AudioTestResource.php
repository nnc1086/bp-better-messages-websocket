<?php

namespace BetterMessages\OpenAI\Testing\Resources;

use BetterMessages\OpenAI\Contracts\Resources\AudioContract;
use BetterMessages\OpenAI\Resources\Audio;
use BetterMessages\OpenAI\Responses\Audio\SpeechStreamResponse;
use BetterMessages\OpenAI\Responses\Audio\TranscriptionResponse;
use BetterMessages\OpenAI\Responses\Audio\TranslationResponse;
use BetterMessages\OpenAI\Testing\Resources\Concerns\Testable;

final class AudioTestResource implements AudioContract
{
    use Testable;

    protected function resource(): string
    {
        return Audio::class;
    }

    public function speech(array $parameters): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function speechStreamed(array $parameters): SpeechStreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function transcribe(array $parameters): TranscriptionResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function translate(array $parameters): TranslationResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
