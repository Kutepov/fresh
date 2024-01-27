<?php namespace common\models\pivot;

use Yii;

/**
 * This is the model class for table "hashtags_sources_urls".
 *
 * @property integer $source_url_id
 * @property integer $hashtag_id
 *
 * @property Hashtags $hashtag
 */
class HashtagSourceUrl extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'hashtags_sources_urls';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['source_url_id'], 'required'],
            [['source_url_id', 'hashtag_id'], 'integer'],
            [['hashtag_id'], 'exist', 'skipOnError' => true, 'targetClass' => Hashtags::className(), 'targetAttribute' => ['hashtag_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'source_url_id' => 'Source Url ID',
            'hashtag_id' => 'Hashtag ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHashtag()
    {
        return $this->hasOne(Hashtags::className(), ['id' => 'hashtag_id']);
    }
}
