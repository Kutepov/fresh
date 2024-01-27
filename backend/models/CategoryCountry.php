<?php namespace backend\models;

use yii;

class CategoryCountry extends \common\models\CategoryCountry
{
    public static function createDefaultsForCountry($country_code): void
    {
        $codes = self::find()->select('country')->distinct()->column();
        $categories = self::find()
            ->select(['category_id as id', 'count(*) as count_countries'])
            ->from(self::tableName())
            ->where(['in', 'country', $codes])
            ->groupBy('category_id')
            ->asArray()
            ->all();

        $resultInsert = [];
        foreach ($categories as $category) {
            if (count($codes) === (int)$category['count_countries']) {
                $resultInsert[] = [$category['id'], $country_code, 0];
            }
        }

        Yii::$app->db->createCommand()
            ->batchInsert(
                self::tableName(),
                ['category_id', 'country', 'articles_exists'],
                $resultInsert)
            ->execute();
    }
}