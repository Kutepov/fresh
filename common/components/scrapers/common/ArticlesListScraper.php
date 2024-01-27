<?php namespace common\components\scrapers\common;

use GuzzleHttp\Promise\PromiseInterface;

interface ArticlesListScraper
{
    /**
     * @param string $url
     * @return PromiseInterface
     */
    public function parseArticlesList(string $url): PromiseInterface;
}