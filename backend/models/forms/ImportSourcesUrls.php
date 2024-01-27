<?php namespace backend\models\forms;

use backend\models\SourceUrl;
use yii\base\Model;
use Yii;

class ImportSourcesUrls extends Model
{
    /**
     * @var string
     */
    public $urls;

    /**
     * @var integer
     */
    public $source_id;

    public function rules()
    {
        return [
            [['urls', 'source_id'], 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'source_id' => 'Источник',
        ];
    }

    public function save()
    {
        $urls = explode("\r\n", $this->urls);
        $rows = [];
        foreach ($urls as $url) {
            $rows[] = [
                'url' => $url,
                'source_id' => $this->source_id
            ];
        }
        Yii::$app->db->createCommand()->batchInsert(SourceUrl::tableName(), ['url', 'source_id'], $rows)->execute();
    }
}