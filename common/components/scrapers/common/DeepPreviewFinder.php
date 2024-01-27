<?php declare(strict_types=1);

namespace common\components\scrapers\common;

use GuzzleHttp\Promise\PromiseInterface;

interface DeepPreviewFinder
{
    public function parseArticlesList(string $url, $isPreviewRequest = false): PromiseInterface;
}