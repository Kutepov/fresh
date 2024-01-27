<?php namespace common\models;

use buzz\models\traits\CategoryMixin;
use common\behaviors\ActiveRecordUUIDBehavior;
use common\behaviors\CarbonBehavior;
use common\components\helpers\Api;
use common\models\pivot\CategoryLang;
use yeesoft\multilingual\db\MultilingualLabelsTrait;
use common\components\multilingual\behaviors\MultilingualBehavior;
use backend\traits\MultilingualModelTrait;
use yii\helpers\Inflector;

/**
 * This is the model class for table "categories".
 *
 * @property integer $id
 * @property string $name
 * @property integer $priority
 * @property string $image
 * @property string $icon
 * @property string $title
 * @property string $read_more_label
 * @property int $updated_at
 *
 * @property-read \common\models\Hashtag $hashtag
 *
 * @property-read CategoryCountry[] $countries
 * @property-read Article[] $articles
 * @property-read Article[] $latestArticles
 * @property-read Folder|null $folder
 * @property-read bool $isDefault
 * @property-read SourceUrl[] $sourcesUrls
 * @property-read SourceUrl[] $enabledSourcesUrls
 * @property-read CategoryLang[] $languagesPivot
 * @property-read CategoryLang[] $translations
 * @property-read array $urlLanguages
 * @property-read string $displayName
 */
class Category extends \yii\db\ActiveRecord
{
    use CategoryMixin;
    use MultilingualLabelsTrait;
    use MultilingualModelTrait;

    public const DEFAULT_CATEGORY_NAME = 'default';
    public const DEFAULT_CATEGORY_ID = '856995dc-e0d0-4c21-9bc3-aae8a53f4f5d';
    public const USER_SOURCE_SLUG = 'u';
    public const SCENARIO_LATEST_ARTICLES_DEPRECATED = 'latestArticles';

    public const SCENARIO_CATALOG = 'catalog';

    public $top = false;

    public $country = null;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'categories';
    }

    public function behaviors()
    {
        return [
            'multilingual' => [
                'class' => MultilingualBehavior::class,
                'attributes' => [
                    'title',
                    'hashtag_id'
                ]
            ],
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
            ActiveRecordUUIDBehavior::class
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_LATEST_ARTICLES_DEPRECATED] = $scenarios[self::SCENARIO_DEFAULT];
        $scenarios[self::SCENARIO_CATALOG] = $scenarios[self::SCENARIO_DEFAULT];
        return $scenarios;
    }

    public function fields()
    {
        /** @deprecated */
        if ($this->scenario == self::SCENARIO_LATEST_ARTICLES_DEPRECATED) {
            return [
                'category' => function () {
                    return [
                        'name' => $this->name,
                        'translatedName' => $this->title,
                        'readMoreText' => $this->read_more_label
                    ];
                },
                'items' => function () {
                    return $this->latestArticles;
                }
            ];
        }

        if (Api::version(Api::V_2_20) && $this->scenario === self::SCENARIO_CATALOG) {
            $fields = [
                'title' => function () {
                    return $this->displayName;
                },
                'image',
                'icon',
                'top',
                'hashtag' => function () {
                    return $this->hashtag ? '#' . $this->hashtag->tag : null;
                }
            ];
        } else {
            $fields = [
                'name',
                'translatedName' => 'title'
            ];
        }

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            ['title', 'string'],
            ['read_more_label', 'string'],
            ['priority', 'integer'],
            [['name'], 'string', 'max' => 160],
            ['name', 'match', 'pattern' => '/^[a-z-0-9]*$/', 'message' => 'Только строчные латинские буквы, цифры и знак тире'],
            ['name', 'match', 'pattern' => '/^(?!u$).*$/', 'message' => 'Категория не может называться "u".'],
            [['image', 'icon'], 'string', 'max' => 320],
            [['countriesList'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'old_id' => 'Old ID',
            'name' => 'ЧПУ URL',
            'priority' => 'Приоритет',
            'image' => 'Изображение',
            'icon' => 'Иконка папки',
            'title' => 'Заголовок',
            'countriesList' => 'Страны',
        ];
    }

    public function getCountriesList()
    {
        return CategoryCountry::find()
            ->select('country')
            ->where(['category_id' => $this->id])
            ->column();
    }

    public function setCountriesList($data)
    {
        $this->countriesList = $data;
    }


    public function getCountries()
    {
        return $this->hasMany(CategoryCountry::class, [
            'category_id' => 'id'
        ])->joinWith('countryModel')->orderBy(['countries.name' => SORT_ASC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getArticles()
    {
        return $this->hasMany(Article::class, [
            'category_id' => 'id'
        ]);
    }

    public function getLatestArticles()
    {
        return $this
            ->getArticles()
            ->newestFirst();
    }

    public function getHashtag()
    {
        return $this->hasOne(Hashtag::class, [
            'id' => 'hashtag_id'
        ]);
    }

    public static function find()
    {
        return new \common\queries\Category(get_called_class());
    }

    public function getFolder()
    {
        return $this->hasOne(Folder::class, [
            'category_id' => 'id'
        ]);
    }

    public function getSourcesUrls(): \common\queries\SourceUrl
    {
        return $this->hasMany(SourceUrl::class, [
            'category_id' => 'id'
        ])->byCountry($this->country);
    }

    public function getEnabledSourcesUrls(): \common\queries\SourceUrl
    {
        return $this->getSourcesUrls()->enabled();
    }

    public function getIsDefault(): bool
    {
        return $this->name === self::DEFAULT_CATEGORY_NAME;
    }

    public function getLanguagesPivot()
    {
        return $this->hasMany(CategoryLang::class, [
            'owner_id' => 'id'
        ]);
    }

    public function getUrlLanguages(): array
    {
        $languages = [];

        foreach ($this->countries as $country) {
            foreach ($country->countryModel->urlLanguages as $urlLanguage) {
                $languages[] = $urlLanguage;
            }
        }

        return $languages;
    }

    public function getDisplayName(): string
    {
        return $this->title ?: Inflector::humanize(str_replace('-', '_', $this->name));
    }
}
