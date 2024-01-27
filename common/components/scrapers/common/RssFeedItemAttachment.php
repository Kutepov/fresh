<?php declare(strict_types=1);

namespace common\components\scrapers\common;

/**
 * @property-read bool $isImage
 * @property-read bool $isVideo
 */
class RssFeedItemAttachment extends \yii\base\BaseObject
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_OTHER = 'other';

    public string $url;
    public string $type;
    public $size;

    public function __construct($config = [])
    {
        [$type] = explode('/', $config['type']);
        switch ($type) {
            case 'image':
                $config['type'] = self::TYPE_IMAGE;
                break;

            case 'video':
                $config['type'] = self::TYPE_VIDEO;
                break;

            default:
                $config['type'] = self::TYPE_OTHER;
                break;
        }
        parent::__construct($config);
    }

    public function getIsImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function getIsVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public function getPreview(): ?string
    {
        if (in_array($this->type, [self::TYPE_IMAGE, self::TYPE_VIDEO])) {
            if (preg_match('#^(https?://[^/]+:9504/media/[^/]+/\d+/)#iu', $this->url, $m)) {
                return $m[1] . 'preview/thumb.jpeg';
            }
        }

        return null;
    }
}