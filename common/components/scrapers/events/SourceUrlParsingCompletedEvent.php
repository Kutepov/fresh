<?php namespace common\components\scrapers\events;

use Assert\Assertion;
use common\components\scrapers\dto\ArticleItem;
use common\models\Source;
use common\models\SourceUrl;
use yii\base\Event;

/**
 * Class SourceParsingCompletedEvent
 * @package common\components\scrapers\events
 *
 * @property ArticleItem[] $articlesItems
 * @property SourceUrl $sourceUrl
 */
class SourceUrlParsingCompletedEvent extends Event
{
    public $articlesItems = [];
    public $sourceUrl;

    const NAME = 'event.sources.parsing.completed';

    public function __construct(array $articlesItems, SourceUrl $sourceUrl, $config = [])
    {
        Assertion::allIsInstanceOf($articlesItems, ArticleItem::class);

        $this->articlesItems = $articlesItems;
        $this->sourceUrl = $sourceUrl;

        parent::__construct($config);
    }
}