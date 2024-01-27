<?php

declare(strict_types=1);

namespace common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper;

class BaseUrls
{
    /**
     * @var array
     */
    private $imageUrls;
    /**
     * @var array
     */
    private $videoUrls;

    public function __construct()
    {
        $this->imageUrls = [];
        $this->videoUrls = [];
    }

    public function addImageUrl(...$url): void
    {
        foreach ($url as $key => $value) {
            $this->imageUrls[] = $value;
        }
    }

    public function addVideoUrl(...$url): void
    {
        foreach ($url as $key => $value) {
            $this->videoUrls[] = $value;
        }
    }

    public function getImageUrls(): array
    {
        return $this->imageUrls;
    }

    public function getVdeoUrls(): array
    {
        return $this->videoUrls;
    }
}
