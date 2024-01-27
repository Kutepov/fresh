<?php namespace common\models\pivot;

use Yii;
use common\models\Source;

/**
 * This is the model class for table "sources_subscribers".
 *
 * @property string $created_at
 * @property string $source_id
 * @property integer $app_id
 *
 * @property Source $source
 */
class SourceSubscription extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources_subscribers';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['source_id', 'app_id'], 'required'],
            [['app_id'], 'integer'],
            [['source_id'], 'string', 'max' => 36],
            [['source_id', 'app_id'], 'unique', 'targetAttribute' => ['source_id', 'app_id']],
            [['source_id'], 'exist', 'skipOnError' => false, 'targetClass' => Source::class, 'targetAttribute' => ['source_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'created_at' => 'Created At',
            'source_id' => 'Source ID',
            'app_id' => 'App ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSource()
    {
        return $this->hasOne(Source::class, ['id' => 'source_id']);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            $this->source->updateCounters([
               'subscribers_count' => 1
            ]);
        }
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            $this->source->updateCounters([
                'subscribers_count' => -1
            ]);

            return true;
        }

        return false;
    }
}
