<?php declare(strict_types=1);

namespace common\services\feeds;

use common\components\scrapers\common\RssScraper;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\TelegramScraper;
use common\components\scrapers\common\YoutubeRssScraper;
use common\models\Source;
use common\models\SourceUrl;

class FeedItem
{
    public $feedUrl;
    public $url;
    public $title;
    private $icon;

    private $sourceSiteTitle;

    /** @var HashImageService */
    private $hashImageService;

    /**
     * @param $url
     * @param $title
     */
    public function __construct($url, $feedUrl, ?string $title = null, ?string $icon = null)
    {
        $this->hashImageService = \Yii::$container->get(HashImageService::class);

        $this->url = $url;
        $this->feedUrl = $feedUrl;
        $this->title = trim($title);

        if ($icon) {
            $this->icon = hashedImageUrl($this->hashImageService->hashImage($icon));
        }
    }

    /**
     * @return mixed
     */
    public function getSourceSiteTitle()
    {
        return $this->sourceSiteTitle;
    }

    /**
     * @param mixed $sourceSiteTitle
     */
    public function setSourceSiteTitle($sourceSiteTitle): void
    {
        $this->sourceSiteTitle = trim($sourceSiteTitle);
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        if ($icon) {
            $this->icon = hashedImageUrl($this->hashImageService->hashImage($icon));
        } else {
            $this->icon = null;
        }
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): void
    {
        $this->title = trim($title);
    }

    /**
     * @return mixed
     */
    public function getFeedUrl()
    {
        return $this->feedUrl;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }

    public function getType(): string
    {
        if (clearHost($this->url) === 't.me') {
            return Source::TYPE_TELEGRAM;
        }

        if (in_array(clearHost($this->url), ['youtube.com', 'youtu.be'])) {
            return Source::TYPE_YOUTUBE_PREVIEW;
        }

        return Source::TYPE_WEBVIEW;
    }

    public function getParserClass(): string
    {
        if (clearHost($this->url) === 't.me') {
            return TelegramScraper::class;
        }

        if (in_array(clearHost($this->url), ['youtube.com', 'youtu.be'])) {
            return YoutubeRssScraper::class;
        }

        return RssScraper::class;
    }

    public function getSourceUrlInstance(): SourceUrl
    {
        return new SourceUrl([
            'id' => $this->createFeedId(),
            'image' => $this->icon,
            'name' => $this->title,
            'type' => $this->getType(),
            'url' => $this->url
        ]);
    }

    public function createFeedId(): string
    {
        if (clearHost($this->url) === 't.me') {
            return 'telegram://' . $this->url;
        }

        if (in_array(clearHost($this->url), ['youtube.com', 'youtu.be'])) {
            return 'youtube://' . $this->url;
        }

        return 'rss://' . $this->feedUrl;
    }
}