<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper;

class TwitterHelper
{
    public function generateUrlForId(string $id): string
    {
        return "https://twitter.com/i/web/status/{$id}";
    }
}
