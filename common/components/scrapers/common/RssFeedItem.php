<?php declare(strict_types=1);

namespace common\components\scrapers\common;

use Carbon\Carbon;
use yii\base\BaseObject;

class RssFeedItem extends BaseObject
{
    public ?string $id;
    public string $title;
    public ?string $description;
    public ?string $image;
    public ?string $author;
    public string $url;
    public Carbon $date;
    /** @var RssFeedItemAttachment[] */
    public array $attachments = [];
}