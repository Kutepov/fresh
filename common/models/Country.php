<?php namespace common\models;

use common\behaviors\CarbonBehavior;
use common\components\caching\Cache;
use common\components\helpers\Api;
use common\exceptions\CountryNotFoundException;
use common\models\pivot\CountryLanguage;
use Yii;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "countries".
 *
 * @property integer $id
 * @property string $code
 * @property string $name
 * @property string $image
 * @property integer $priority
 * @property string $timezone
 * @property string $locale
 * @property \Carbon\Carbon $top_calculated_at
 * @property boolean $top_locked
 * @property string $articles_preview_type
 * @property boolean $articles_preview_type_switcher
 * @property boolean $quality_survey
 * @property integer $quality_survey_good
 * @property integer $quality_survey_bad
 *
 * @property-read array $urlLanguages
 * @property-read string|null $localeLanguage
 * @property-read Language[] $articlesLanguages
 * @property-read Language|null $defaultArticlesLanguage
 * @property-read CountryLanguage[] $countryLanguages
 * @property-read Category[] $categories
 * @property-read CategoryCountry[] $categoriesCountries
 */
class Country extends \yii\db\ActiveRecord
{
    public const PREVIEW_TYPE_BIG = 'big';
    public const PREVIEW_TYPE_SMALL = 'small';

    public const PREVIEW_TYPES = [
        self::PREVIEW_TYPE_BIG,
        self::PREVIEW_TYPE_SMALL
    ];

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'countries';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['top_calculated_at', 'created_at', 'updated_at']
            ],
        ];
    }

    public function fields()
    {
        $fields = [
            'code',
            'name',
            'image'
        ];

        if (Api::version(Api::V_2_01)) {
            $fields['articlesLanguages'] = function () {
                return $this->articlesLanguages ?: null;
            };
        }

        if (Api::version(Api::V_2_08)) {
            $fields['articlesPreviewType'] = 'articles_preview_type';
            $fields['articlesPreviewTypeSwitcher'] = function () {
                return (bool)$this->articles_preview_type_switcher;
            };
        }

        $fields['qualitySurvey'] = function () {
            return (bool)$this->quality_survey;
        };

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['code'], 'string', 'max' => 2],
            [['name'], 'string', 'max' => 48],
            ['locale', 'string', 'max' => 5],
            [['code'], 'unique'],
            [['image', 'timezone'], 'string', 'max' => 32],
            ['priority', 'integer'],
            [['code', 'name', 'timezone'], 'required'],
            [['code'], 'filter', 'filter' => function ($value) {
                return mb_strtoupper($value);
            }]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'code' => 'Код',
            'name' => 'Название',
            'image' => 'Флаг',
            'timezone' => 'Часовой пояс',
            'priority' => 'Приоритет',
            'articles_preview_type' => 'Внешний вид списка новостей',
            'articles_preview_type_switcher' => 'Переключатель внешнего вида',
            'quality_survey' => 'Опрос о качестве новостей'
        ];
    }

    public static function find()
    {
        return new \common\queries\Country(get_called_class());
    }

    /**
     * @return self[]
     */
    public static function all(): array
    {
        return self::find()->orderBy('code')->all();
    }

    public function getDefaultArticlesLanguage(): ?Language
    {
        return $this
            ->getArticlesLanguages()
            ->andWhere([
                'countries_languages.default' => 1
            ])
            ->one();
    }

    public function getArticlesLanguages(): ActiveQuery
    {
        return $this
            ->hasMany(Language::class, [
                'id' => 'language_id'
            ])
            ->via('countryLanguages')
            ->orderBy(['countries_languages.default' => SORT_DESC])
            ->joinWith([
                'countryLanguages' => function (ActiveQuery $query) {
                    $query->andWhere(['countries_languages.country_id' => $this->id]);
                }
            ])->cache(
                Cache::DURATION_LANGUAGES_LIST,
                new TagDependency(['tags' => [Cache::TAG_LANGUAGES_LIST]])
            );
    }

    public function isArticlesLanguageExists($code): bool
    {
        $existsCodes = ArrayHelper::getColumn($this->articlesLanguages, 'code');

        return in_array($code, $existsCodes, true);
    }

    public function getCountryLanguages(): ActiveQuery
    {
        return $this->hasMany(CountryLanguage::class, [
            'country_id' => 'id'
        ]);
    }

    public function getCategoriesCountries()
    {
        return $this->hasMany(CategoryCountry::class, ['country' => 'code']);
    }

    public function getCategories(): ActiveQuery
    {
        return $this->hasMany(Category::class, [
            'id' => 'category_id'
        ])->via('categoriesCountries');
    }

    public static function findByCode($code): self
    {
        /** @var self $country */
        $country = self::find()
            ->andWhere([
                'code' => $code
            ])
            ->cache(
                Cache::DURATION_COUNTRY,
                new TagDependency(['tags' => Cache::TAG_COUNTRY])
            )
            ->one();

        if (!$country) {
            throw new CountryNotFoundException('Country with code "' . $code . '" not found.');
        }

        return $country;
    }

    /**
     * @return string
     */
    public function getAbsoluteUrlToImage()
    {
        return Yii::getAlias('@frontendBaseUrl') . '/img/' . $this->image;
    }

    public function lockForTopCalculation(): void
    {
        $this->updateAttributes([
            'top_locked' => 1
        ]);
    }

    public function unlockForTopCalculation(): void
    {
        $this->updateAttributes([
            'top_locked' => 0
        ]);
    }

    public function getLocaleLanguage(): ?string
    {
        if ($this->locale) {
            [$language] = explode('_', $this->locale);

            return $language;
        }
        return null;
    }

    public function getUrlLanguages(): array
    {
        $result = [];

        if (!$this->articlesLanguages) {
            $result[] = strtolower($this->code);
        }
        else {
            foreach ($this->articlesLanguages as $articlesLanguage) {
                $result[] = $articlesLanguage->code . '-' . strtolower($this->code);
            }
        }

        return $result;
    }
}
