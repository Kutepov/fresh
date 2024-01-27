<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper;

class RamblerHelper
{
    public function generateUrlForId(string $id): string
    {
        return "https://vp.rambler.ru/player/0.2.20/iframe.html?id=$id";
    }
}
