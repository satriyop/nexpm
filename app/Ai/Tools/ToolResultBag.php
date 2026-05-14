<?php

namespace App\Ai\Tools;

class ToolResultBag
{
    public ?string $toolName = null;

    /** @var array<string, mixed> */
    public array $toolPayload = [];
}
