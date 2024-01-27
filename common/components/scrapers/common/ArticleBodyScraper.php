<?php namespace common\components\scrapers\common;

use GuzzleHttp\Promise\PromiseInterface;

interface ArticleBodyScraper
{
    /**
     * @param string $url
     * @return PromiseInterface
     */
    public function parseArticleBody(string $url): PromiseInterface;
}