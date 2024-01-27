<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "hashtags".
 *
 * @property integer $id
 * @property string $tag
 */
class Hashtag extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'hashtags';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tag'], 'string', 'max' => 128],
            [['tag'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tag' => 'Tag',
        ];
    }

    public static function findByTag(string $tag): ?self
    {
        return self::find()->where([
            'tag' => $tag
        ])->one();
    }
}
