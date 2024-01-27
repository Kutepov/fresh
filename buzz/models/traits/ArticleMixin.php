<?php namespace buzz\models\traits;

use common\components\scrapers\dto\ArticleBodyNode;
use common\models\Category;
use common\models\Source;

/**
 * @see \common\models\Article
 *
 * @property-read string $publicationDateLabel
 * @property-read array $route
 * @property-read array $sharingRoute
 * @property-read string $metaDescription
 * @property-read string $urlLanguage
 */
trait ArticleMixin
{
    public function getPublicationDateLabel(): string
    {
        $createdAt = $this->created_at->locale(\Yii::$app->language)->setTimezone(CURRENT_TIMEZONE);
        if ($this->created_at->isToday()) {
            $prefix = \t('сегодня');
            $format = 'LT';
        } elseif ($this->created_at->isYesterday()) {
            $prefix = \t('вчера');
            $format = 'LT';
        } else {
            $prefix = null;
            $format = 'L, LT';
        }

        return ($prefix ? $prefix . ', ' : '') . $createdAt->isoFormat($format);
    }

    public function getRoute(): array
    {
        if ($this->slug) {
            $route = ['articles/view', 'slug' => $this->slug, 'categorySlug' => $this->category_name];
        } else {
            $route = ['articles/view', 'id' => $this->id, 'categorySlug' => $this->category_name];
        }

        if (!$this->source->default) {
            $route['categorySlug'] = Category::USER_SOURCE_SLUG;
        }

        return $route;
    }

    public function getSharingRoute(): array
    {
        $route = $this->route;
        $route['language'] = $this->urlLanguage;

        return $route;
    }

    public function getUrlLanguage(): string
    {
        $lang = [];
        if ($this->source->language) {
            $lang[] = $this->source->language;
        }
        $lang[] = strtolower($this->source->country);

        return implode('-', $lang);
    }

    public function getScaledPreviewImage($height = null, $ratio = 1)
    {
        if ($this->source_id === '6df8055e-2558-46a9-9bf5-d6657665a85f') {
            return null;
        }

        $url = 'https://stx.myfresh.app/h/';

        if ($height) {
            $url .= ($height * $ratio) . '/';
        }

        return $url . $this->preview_image;
    }

    public function getBuzzBody(): array
    {
        $body = [];

        if (!in_array($this->source->type, [Source::TYPE_YOUTUBE, Source::TYPE_YOUTUBE_PREVIEW], true) && $this->preview_image) {
            $body[] = (new ArticleBodyNode(self::BODY_PART_IMAGE, $this->preview_image))->jsonSerialize();
        }

        if ($this->source->type === Source::TYPE_FULL_ARTICLE) {
            foreach ($this->body as $k => $node) {
                if ($node['elementName'] === self::BODY_PART_PARAGRAPH) {
                    $body[] = (new ArticleBodyNode(self::BODY_PART_PARAGRAPH, $node['value']))->jsonSerialize();
                    if (count($body) === 3) {
                        return $body;
                    }
                }
            }
        }

        if (count($body)) {
            return $body;
        }

        return $this->getPreparedBody();
    }

    public function getMetaDescription(): ?string
    {
        if ($this->description) {
            return $this->description;
        }

        return $this->bodyAsString;
    }
}