<?php declare(strict_types=1);

namespace common\services\feeds;

interface FeedFinder
{
    public function validateAndFixUrlIfNeeded(string &$url): bool;

    public function buildFeedUrl($identifier): string;

    /**
     * @param string $url
     * @return FeedItem[]
     */
    public function findByUrl(string $url): array;

    public function getArticlesType(): string;
}