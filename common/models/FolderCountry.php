<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "folders_countries".
 *
 * @property string $folder_id
 * @property string $country
 * @property integer $articles_exists
 *
 * @property-read Folder $folder
 */
class FolderCountry extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'folders_countries';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['folder_id', 'country'], 'required'],
            [['articles_exists'], 'integer'],
            [['folder_id'], 'string', 'max' => 36],
            [['country'], 'string', 'max' => 2],
            [['folder_id', 'country'], 'unique', 'targetAttribute' => ['folder_id', 'country']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'folder_id' => 'Folder ID',
            'country' => 'Country',
            'articles_exists' => 'Articles Exists',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFolder()
    {
        return $this->hasOne(Folder::class, ['id' => 'folder_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountryModel()
    {
        return $this->hasOne(Country::class, ['code' => 'country']);
    }
}
