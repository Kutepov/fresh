<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper;

class InstagramHelper
{
    public function generateUrlForId(string $id): string
    {
        return "https://www.instagram.com/p/$id/embed/captioned";
    }

    public function generateEmbedUrl(string $url): string
    {
        $withQuestionMark = stristr($url, '?', true);

        if ($withQuestionMark) {
            $url = $withQuestionMark;
        }

        if ('/' !== substr($url, -1)) {
            $url = $url.'/';
        }

        if (false === strpos($url, 'embed')) {
            $url = $url.'embed/';
        }

        return $url;
    }
}
