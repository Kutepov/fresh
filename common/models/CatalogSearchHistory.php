<?php namespace common\models;

use Carbon\Carbon;
use common\behaviors\CarbonBehavior;
use common\services\feeds\FeedFinderService;
use Yii;
use yii\helpers\Html;

/**
 * This is the model class for table "catalog_search_history".
 *
 * @property integer $id
 * @property Carbon $created_at
 * @property integer $app_id
 * @property string $query
 * @property string $section
 * @property string $type
 *
 * @property-read string $typeLabel
 * @property-read string $sectionLabel
 * @property-read string $formattedQuery
 * @property-read App $app
 */
class CatalogSearchHistory extends \yii\db\ActiveRecord
{
    public const TYPE_HASHTAG = 'hashtag';
    public const TYPE_KEYWORD = 'keyword';

    public const TYPE_URL = 'url';

    public const AVAILABLE_TYPES = [
        self::TYPE_URL => 'URL',
        self::TYPE_KEYWORD => 'Ключевое слово',
        self::TYPE_HASHTAG => 'Хештег'
    ];

    public const AVAILABLE_SECTIONS = FeedFinderService::SCRAPERS_LABELS;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'catalog_search_history';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['app_id'], 'integer'],
            [['query', 'section', 'type'], 'string', 'max' => 255],
        ];
    }

    public function getApp()
    {
        return $this->hasOne(App::class, [
            'id' => 'app_id'
        ]);
    }

    public function getTypeLabel(): string
    {
        switch ($this->type) {
            case self::TYPE_URL:
                return 'URL';

            case self::TYPE_HASHTAG:
                return 'Хештег';

            case self::TYPE_KEYWORD:
                return 'Ключевое слово';
        }

        return '&mdash;';
    }

    public function getSectionLabel(): string
    {
        return self::AVAILABLE_SECTIONS[$this->section];
    }

    public function getFormattedQuery(): string {
        if ($this->type === self::TYPE_URL) {
            return Html::a($this->query, $this->query, [
                'target' => '_blank',
                'rel' => 'nofollow',
                'style' => 'text-decoration: underline'
            ]);
        }

        return $this->query;
    }
}
