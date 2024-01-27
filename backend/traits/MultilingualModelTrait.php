<?php namespace backend\traits;

use backend\models\Language;
use yii\db\Query;

trait MultilingualModelTrait
{
    public function getEmptyLanguages($attribute = 'language')
    {
        $currentLanguages = (new Query())->select([$attribute])
            ->from($this->tableName() . '_lang')
            ->where(['not', ['title' => '']])
            ->andWhere(['owner_id' => $this->id])
            ->column();
        $languages = Language::find()
            ->select('code')
            ->where(['not in', 'code', $currentLanguages])
            ->asArray()
            ->column();
        return $languages;
    }
}