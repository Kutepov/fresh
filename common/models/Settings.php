<?php namespace common\models;

use Yii;

/**
 * This is the model class for table "settings".
 *
 * @property integer $top_ctr_update_for_comment
 * @property integer $top_ctr_update_for_rating
 */
class Settings extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'settings';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['top_ctr_update_for_comment', 'top_ctr_update_for_rating'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'top_ctr_update_for_comment' => 'Увеличение CTR новости в топе для каждого комментария',
            'top_ctr_update_for_rating' => 'Увеличение CTR новости в топе для лайка/дизлайка',
        ];
    }
}
