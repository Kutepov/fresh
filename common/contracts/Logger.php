<?php namespace common\contracts;

use common\components\scrapers\common\Scraper;
use common\components\scrapers\dto\ArticleBody;

interface Logger
{
    const ELASTICSEARCH = 'elasticsearch';

    public function scraperError($message, $scraperId = null);

    public function scraperCriticalError($message, $scraperId = null);

    public function critical($message, $tags = []);

    public function warning($message, $tags = []);

    public function info($message, $tags = []);

    public function debug($message, $tags = []);

    public function scraperArticleItemException(\Throwable $exception, Scraper $scraper, ?string $url = null): void;

    public function scraperArticleBodyException(\Throwable $exception, Scraper $scraper, ?string $url = null): void;

    public function scraperPossibleWrongArticleBody(Scraper $scraper, ArticleBody $body, ?string $url = null): void;

    public function scraperEmptyArticleBody(Scraper $scraper, ?string $url = null): void;
}