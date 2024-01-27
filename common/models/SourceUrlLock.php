<?php namespace common\models;

use Carbon\Carbon;
use common\components\validators\TimestampValidator;
use Yii;
use yii2mod\behaviors\CarbonBehavior;

/**
 * This is the model class for table "sources_urls_locks".
 *
 * @property integer $id
 * @property string $source_id
 * @property integer $source_url_id
 * @property Carbon $locked_at
 * @property Carbon $unlocked_at
 * @property string $lock_time
 * @property integer $unlocked_by_cron
 * @property integer $errors
 * @property integer $articles_found
 */
class SourceUrlLock extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources_urls_locks';
    }

    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => [
                    'locked_at',
                    'unlocked_at'
                ]
            ]
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_UPDATE
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['source_id', 'source_url_id'], 'required'],
            [['locked_at', 'unlocked_at'], TimestampValidator::class],
            [['source_url_id', 'unlocked_by_cron', 'errors', 'articles_found'], 'integer'],
            [['lock_time'], 'safe'],
            [['source_id'], 'string', 'max' => 36],
            [['locked_at', 'source_url_id'], 'unique', 'targetAttribute' => ['locked_at', 'source_url_id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'source_id' => 'Source ID',
            'source_url_id' => 'Source Url ID',
            'locked_at' => 'Locked At',
            'unlocked_at' => 'Unlocked At',
            'lock_time' => 'Lock Time',
            'unlocked_by_cron' => 'Unlocked By Cron',
            'errors' => 'Errors',
            'articles_found' => 'Articles Found',
        ];
    }
}
