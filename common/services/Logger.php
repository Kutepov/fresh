<?php namespace common\services;

use common\components\scrapers\common\Scraper;
use common\components\scrapers\dto\ArticleBody;
use common\models\SourceException;
use yii;

class Logger implements \common\contracts\Logger
{
    public function critical($message, $tags = [])
    {
        Yii::error($message);
        $this->flush();
    }

    public function warning($message, $tags = [])
    {
        Yii::warning($message);
        $this->flush();
    }

    public function info($message, $tags = [])
    {
        Yii::info($message);
        $this->flush();
    }

    public function debug($message, $tags = [])
    {
        Yii::debug($message);
        $this->flush();
    }

    public function scraperError($message, $scraperId = null)
    {
        Yii::error($message, 'scrapers');
    }

    public function scraperCriticalError($message, $scraperId = null)
    {
        Yii::error($message, 'scrapers-critical');
    }

    public function scraperArticleItemException(\Throwable $exception, Scraper $scraper, ?string $url = null): void
    {
        SourceException::create(SourceException::ARTICLE_ITEM, $scraper, $exception, $url);
    }

    public function scraperArticleBodyException(\Throwable $exception, Scraper $scraper, ?string $url = null): void
    {
        SourceException::create(SourceException::ARTICLE_BODY, $scraper, $exception, $url);
    }

    public function scraperArticleDescriptionException(\Throwable $exception, Scraper $scraper, ?string $url = null): void
    {
        SourceException::create(SourceException::ARTICLE_DESCRIPTION, $scraper, $exception, $url);
    }

    public function scraperPossibleWrongArticleBody(Scraper $scraper, ArticleBody $body, ?string $url = null): void
    {
        SourceException::createWarning(SourceException::WRONG_ARTICLE_BODY, $scraper, $body->asArray(), $url);
    }

    public function scraperEmptyArticleBody(Scraper $scraper, ?string $url = null): void
    {
        SourceException::createWarning(SourceException::EMPTY_ARTICLE_BODY, $scraper, null, $url);
    }

    private function flush()
    {
        Yii::getLogger()->flush(true);
    }
}