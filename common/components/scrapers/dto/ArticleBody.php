<?php namespace common\components\scrapers\dto;

use common\models\Article;
use yii\helpers\Json;

/**
 * Class ArticleBody
 * @package common\components\scrapers\dto
 */
class ArticleBody implements \JsonSerializable
{
    /**
     * @var ArticleBodyNode[]
     */
    private $nodes;

    public function __construct($nodes = [])
    {
        $this->nodes = [];

        foreach ($nodes as $node) {
            $this->add(new ArticleBodyNode(
                $node['elementName'],
                $node['value']
            ));
        }

        $this->nodes = array_values(array_unique($this->nodes, SORT_REGULAR));
    }

    public function getMediaForTelegram(): array
    {
        $media = [];

        foreach ($this->nodes as $node) {
            switch ($node->getElementName()) {
                case Article::BODY_PART_IMAGE:
                    $media[] = $this->telegramMediaPhoto($node->getValue());
                    break;

                case Article::BODY_PART_CAROUSEL:
                    foreach ($node->getValue() as $item) {
                        $media[] = $this->telegramMediaPhoto($item);
                    }
                    break;

                case Article::BODY_PART_VIDEO_SOURCE:
                    $media[] = [
                        'type' => 'video',
                        'media' => $node->getValue()
                    ];
                    break;
            }
        }
        return $media;
    }

    private function telegramMediaPhoto($url): array
    {
        return [
            'type' => 'photo',
            'media' => 'https://stx.myfresh.app/' . $url
        ];
    }

    public function add(ArticleBodyNode $node): void
    {
        $this->nodes[] = $node;
        $this->nodes = array_values(array_unique($this->nodes, SORT_REGULAR));
    }

    public function getFirstImage(): ?string
    {
        foreach ($this->nodes as $node) {
            switch ($node->getElementName()) {
                case Article::BODY_PART_CAROUSEL:
                    if (isset($node->getValue()[0])) {
                        return $node->getValue()[0];
                    }
                    break;

                case Article::BODY_PART_IMAGE:
                    return $node->getValue();
            }
        }

        return null;
    }

    /**
     * @return ArticleBodyNode[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function asArray(): array
    {
        return Json::decode(Json::encode($this));
    }

    public function jsonSerialize()
    {
        return $this->getNodes();
    }
}