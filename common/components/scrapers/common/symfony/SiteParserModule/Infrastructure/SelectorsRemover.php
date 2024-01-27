<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\SiteParserModule\Infrastructure;

use Symfony\Component\DomCrawler\Crawler;

class SelectorsRemover
{
    public function remove(string $removeSelector, Crawler $textNode): void
    {
        $textNode->filterXPath($removeSelector)->each(
            static function (Crawler $crawler) {
                foreach ($crawler as $node) {
                    $parent = $node->parentNode;
                    if (!$parent) {
                        return;
                    }
                    $parent->removeChild($node);
                }
            }
        );
    }
}
