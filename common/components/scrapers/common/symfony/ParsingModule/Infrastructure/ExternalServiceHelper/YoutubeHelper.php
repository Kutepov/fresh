<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper;

class YoutubeHelper
{
    public function generateUrlForId(string $id): string
    {
        if (stripos($id, 'watch?v=') !== false) {
            $id = explode('watch?v=', $id)[1];
        }

        return "https://www.youtube.com/embed/$id";
    }
}
