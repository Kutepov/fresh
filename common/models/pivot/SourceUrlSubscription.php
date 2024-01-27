<?php namespace common\models\pivot;

use common\models\App;
use common\models\SourceUrl;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "sources_urls_subscribers".
 *
 * @property string $created_at
 * @property integer $source_url_id
 * @property integer $app_id
 * @property bool|integer $push
 *
 * @property-read SourceUrl $sourceUrl
 * @property-read App $app
 */
class SourceUrlSubscription extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sources_urls_subscribers';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['source_url_id', 'app_id'], 'required'],
            [['source_url_id', 'app_id'], 'integer'],
            [['source_url_id', 'app_id'], 'unique', 'targetAttribute' => ['source_url_id', 'app_id']],
            ['push', 'boolean'],
            [['source_url_id'], 'exist', 'skipOnError' => false, 'targetClass' => SourceUrl::class, 'targetAttribute' => ['source_url_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'created_at' => 'Created At',
            'source_url_id' => 'Source Url ID',
            'app_id' => 'App ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSourceUrl()
    {
        return $this->hasOne(SourceUrl::class, [
            'id' => 'source_url_id'
        ]);
    }

    public function getApp()
    {
        return $this->hasOne(App::class, [
            'id' => 'app_id'
        ]);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            if (!$this->app->isSubscribedToSource($this->sourceUrl->source)) {
                (new SourceSubscription([
                    'source_id' => $this->sourceUrl->source_id,
                    'app_id' => $this->app_id
                ]))->save();
            }

            $this->sourceUrl->updateCounters([
                'subscribers_count' => 1
            ]);
        }
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            $sourceUrlsIds = ArrayHelper::getColumn($this->sourceUrl->source->urls, 'id');
            ArrayHelper::removeValue($sourceUrlsIds, $this->source_url_id);
            if (!$this->app->getSourcesUrlsSubscriptions()->andWhere([
                'source_url_id' => $sourceUrlsIds,
            ])->exists()) {
                if ($existsSourceSubscription = $this->app->getSourcesSubscriptions()->andWhere([
                    'source_id' => $this->sourceUrl->source_id
                ])->one()) {
                    $existsSourceSubscription->delete();
                }
            }

            $this->sourceUrl->updateCounters([
                'subscribers_count' => -1
            ]);

            return true;
        }

        return false;
    }
}
