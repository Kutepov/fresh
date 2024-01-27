<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "categories_countries".
 *
 * @property string $category_id
 * @property string $country
 * @property boolean $articles_exists
 *
 * @property Category $category
 * @property Country $countryModel
 */
class CategoryCountry extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'categories_countries';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['category_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            ['articles_exists', 'boolean'],
            [['category_id'], 'exist', 'skipOnError' => true, 'targetClass' => Category::class, 'targetAttribute' => ['category_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'category_id' => 'Category ID',
            'country' => 'Country',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountryModel()
    {
        return $this->hasOne(Country::class, ['code' => 'country']);
    }
}
