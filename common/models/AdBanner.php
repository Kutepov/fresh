<?php namespace common\models;

use common\behaviors\CarbonBehavior;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "ad_banners".
 *
 * @property integer $id
 * @property integer $enabled
 * @property string $type
 * @property string $platform
 * @property string $country
 * @property string $provider
 * @property integer $position
 * @property integer $repeat_factor
 * @property integer $limit
 * @property array $categories
 * @property string|null $banner_id
 *
 * @property Country $countryModel
 */
class AdBanner extends \yii\db\ActiveRecord
{
    public const TYPE_CATEGORY = 'category';
    public const TYPE_FEED = 'feed';
    public const TYPE_ARTICLE = 'article';
    public const TYPE_ARTICLE_BODY = 'article-body';
    public const TYPE_SIMILAR_ARTICLES = 'similar-articles';
    public const TYPE_FULLSCREEN = 'fullscreen';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';
    public const PROVIDER_GOOGLE_AD = 'googlead';
    public const PROVIDER_RECREATIV = 'recreativ';

    public const TYPES = [
        self::TYPE_ARTICLE => 'Статья',
        self::TYPE_CATEGORY => 'Категория',
        self::TYPE_FEED => 'Фид',
        self::TYPE_ARTICLE_BODY => 'В теле новости',
        self::TYPE_FULLSCREEN => 'Полноэкранный',
        self::TYPE_SIMILAR_ARTICLES => 'В списке "Читайте также"'
    ];

    public const PLATFORMS = [
        self::PLATFORM_ANDROID => 'Android',
        self::PLATFORM_IOS => 'iOS',
    ];

    public const PROVIDERS = [
        self::PROVIDER_GOOGLE_AD => 'AdMob',
        self::PROVIDER_RECREATIV => 'Recreativ',
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ad_banners';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['enabled', 'platform', 'country', 'provider', 'type'], 'required'],
            [['position', 'repeat_factor', 'limit'], 'integer'],
            ['enabled', 'boolean'],
            [['categories'], 'safe'],
            [['type', 'provider'], 'string', 'max' => 32],
            [['platform'], 'string', 'max' => 16],
            [['country'], 'string', 'max' => 2],
            ['categories', 'default', 'value' => []],
            [['position'], 'validatePosition'],
        ];
    }

    public function validatePosition($attribute, $params)
    {
        if ($this->type === self::TYPE_FEED || $this->type === self::TYPE_CATEGORY) {
            $query = self::find()->where([
                'platform' => $this->platform,
                'country' => $this->country,
                'type' => $this->type,
                'position' => $this->position
            ]);
            if (!$this->isNewRecord) {
                $query->andWhere(['not', ['id' => $this->id]]);
            }
            if ($query->count()) {
                $this->addError($attribute, 'Значение не уникально');
            }
        }
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'enabled' => 'Активно',
            'type' => 'Тип',
            'platform' => 'Платформа',
            'country' => 'Страна',
            'provider' => 'Провайдер',
            'position' => 'Позиция',
            'repeat_factor' => 'Фактор повтора',
            'limit' => 'Лимит',
            'categories' => 'Категории',
            'banner_id' => 'ID баннера',
        ];
    }

    public function getCountryModel()
    {
        return $this->hasOne(Country::class, [
            'code' => 'country'
        ]);
    }

    /**
     * @return Category[]
     */
    public function getCategories()
    {
        $categories = Category::find()->multilingual();
        if (is_array($this->categories) && count($this->categories)) {
            $categories->where(['in', 'id', $this->categories]);
        }
        return $categories->all();
    }


    public static function find()
    {
        return new \common\queries\AdBanner(get_called_class());
    }
}
