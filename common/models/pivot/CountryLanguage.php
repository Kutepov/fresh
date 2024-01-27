<?php namespace common\models\pivot;

use common\models\Country;
use common\models\Language;
use Yii;

/**
 * This is the model class for table "countries_languages".
 *
 * @property integer $country_id
 * @property integer $language_id
 * @property integer $default
 *
 * @property Country $country
 * @property Language $language
 */
class CountryLanguage extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'countries_languages';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['country_id', 'language_id'], 'required'],
            [['country_id', 'language_id', 'default'], 'integer'],
            [['country_id', 'language_id'], 'unique', 'targetAttribute' => ['country_id', 'language_id']],
            [['country_id'], 'exist', 'skipOnError' => true, 'targetClass' => Country::class, 'targetAttribute' => ['country_id' => 'id']],
            [['language_id'], 'exist', 'skipOnError' => true, 'targetClass' => Language::class, 'targetAttribute' => ['language_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'country_id' => 'Country ID',
            'language_id' => 'Language ID',
            'default' => 'Default',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(Country::class, ['id' => 'country_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLanguage()
    {
        return $this->hasOne(Language::class, ['id' => 'language_id']);
    }
}
