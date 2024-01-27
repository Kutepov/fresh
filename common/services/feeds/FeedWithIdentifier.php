<?php declare(strict_types=1);

namespace common\services\feeds;

interface FeedWithIdentifier
{
    /**
     * @param string $id
     * @return FeedItem[]
     */
    public function findById(string $id): array;
}