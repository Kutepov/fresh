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
 * @property Source $source
 */
class SourceUrlParsingFailedEvent extends Event
{
    public $sourceUrl;
    public $exception;

    const NAME = 'event.sources.parsing.failed';

    public function __construct(SourceUrl $sourceUrl, \Throwable $exception, $config = [])
    {
        $this->sourceUrl = $sourceUrl;
        $this->exception = $exception;

        parent::__construct($config);
    }
}