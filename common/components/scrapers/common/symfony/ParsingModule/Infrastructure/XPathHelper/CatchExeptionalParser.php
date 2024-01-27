<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper;

use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\Exception\ParsingFailedException;
use Symfony\Component\DomCrawler\Crawler;

class CatchExeptionalParser
{
    public function __construct()
    {
    }

    private const EXEPTION_LINKS = [
        'www.interfax.com.ua',
        'www.interfax.com',
        'www.interfax.by',
        'www.interfax.ru',
        'www.interfax.kz',
    ];

    public function catchexeption(Crawler $catchingNodes): void
    {
        $exeptionLinks = self::EXEPTION_LINKS;
        $catchingNodes->each(function (Crawler $node) use (&$exeptionLinks) {
            if ('a' === $node->nodeName()) {
                $link = $node->attr('href');
                if ($link) {
                    foreach ($exeptionLinks as $value) {
                        if (false !== strpos($link, $value)) {
                            throw new ParsingFailedException('Exeption link, skipping');
                        }
                    }
                }
            }
        });
    }

    public function catcheExeptionByNode(Crawler $html, string $exeptionNode): void
    {
        $hasExeptionNode = $html->filterXPath($exeptionNode)->count();
        if ($hasExeptionNode) {
            throw new ParsingFailedException('Contains exeption node, skipping');
        }
    }
}
