<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\Exception\MissingParserException;
use Symfony\Component\DomCrawler\Crawler;

class NewsCutter
{
    const exclusiveLinks = [
        'interfax.com.ua',
        'interfax.com',
        'interfax.ru',
        'interfax.by',
        'interfax.kz',
    ];

    public function cutterByLinks(Crawler $links)
    {
        $links->each(function (Crawler $node) {
            $src = $node->attr('href') ?? '';
            foreach (self::exclusiveLinks as $value) {
                if (false !== stripos($src, $value)) {
                    throw new MissingParserException("Exclusive link $src");
                }
            }
        });
    }

    public function cutterNewsWithoutImages(Crawler $imagesNodes, string $url = '')
    {
        $imagesCount = $imagesNodes->count();
        if (0 === $imagesCount) {
            throw new MissingParserException('Missing preview ' . $url);
        }
    }
}
