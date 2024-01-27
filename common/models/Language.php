<?php namespace common\models;

use common\components\caching\Cache;
use common\exceptions\CountryNotFoundException;
use common\models\pivot\CountryLanguage;
use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "languages".
 *
 * @property integer $id
 * @property string $code
 * @property string $name
 * @property string $short_name
 * @property string $locale
 *
 * @property CountryLanguage[] $countryLanguages
 * @property Country[] $countries
 */
class Language extends \yii\db\ActiveRecord
{
    public const SCENARIO_VIA_COUNTRY_FORM = 'country_form';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'languages';
    }

    public static function getCodes()
    {
        $key = self::getCacheCode();
        $cache = Yii::$app->cache;

        if ($cache->exists($key)) {
            return Yii::$app->cache->get($key);
        }

        $languages = Language::find()->select(['id', 'code'])->asArray()->all();
        $cache->set($key, $languages);
        return $languages;
    }

    private static function getCacheCode(): string
    {
        return self::tableName() . '_codes';
    }

    private function invalidateCacheCodes(): bool
    {
        $cache = Yii::$app->cache;
        return $cache->delete(self::getCacheCode());
    }

    public function fields()
    {
        $fields = [
            'code',
            'name',
            'shortName' => 'short_name'
        ];

        if ($this->isRelationPopulated('countryLanguages')) {
            $fields['default'] = function () {
                return (bool)$this->countryLanguages[0]->default;
            };
        }

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['code'], 'string', 'max' => 5],
            [['code'], 'unique'],
            ['locale', 'string', 'max' => 5],
            [['name'], 'string', 'max' => 64],
            [['short_name'], 'string', 'max' => 16],
            [['code'], 'required'],
            [['name'], 'required', 'except' => self::SCENARIO_VIA_COUNTRY_FORM],
            [['code'], 'filter', 'filter' => function ($value) {
                return mb_strtolower($value);
            }]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'Код',
            'name' => 'Название',
            'short_name' => 'Аббревиатура'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountryLanguages()
    {
        return $this->hasMany(CountryLanguage::class, ['language_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountries()
    {
        return $this->hasMany(Country::class, ['id' => 'country_id'])->viaTable('countries_languages', ['language_id' => 'id']);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->invalidateCacheCodes();
    }

    public function afterDelete()
    {
        parent::afterDelete();
        $this->invalidateCacheCodes();
    }

    public static function findByCode($code): self
    {
        /** @var self $language */
        $language = self::find()
            ->andWhere([
                'code' => $code
            ])
            ->cache(
                Cache::DURATION_COUNTRY,
                new TagDependency(['tags' => Cache::TAG_LANGUAGES_LIST])
            )
            ->one();

        if (!$language) {
            throw new CountryNotFoundException('Language with code "' . $code . '" not found.');
        }

        return $language;
    }
}
