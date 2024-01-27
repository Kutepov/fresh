<?php namespace common\components\validators;

use common\components\caching\Cache;
use common\models\Category;
use yii\caching\TagDependency;
use yii\validators\Validator;

/**
 * Class CategoryNameToIdConverter
 * @package common\components\validators
 * @deprecated Symfony legacy
 */
class CategoryNameToIdConverter extends Validator
{
    public $categoriesListAttribute = 'category';

    public function validateAttribute($model, $attribute)
    {
        if (!$model->hasErrors($attribute)) {
            if (is_array($model->$attribute) && count($model->$attribute)) {
                /** @deprecated Symfony Legacy - достаем id категорий по их названиям */
                $model->{$this->categoriesListAttribute} = Category::find()
                    ->select('id')
                    ->bySlugName($model->$attribute)
                    ->cache(
                        Cache::DURATION_CATEGORIES_LIST,
                        new TagDependency(['tags' => Cache::TAG_CATEGORIES_LIST])
                    )
                    ->column();
            }
            else {
                if ($category = Category::find()->bySlugName($model->$attribute)->cache(
                    Cache::DURATION_CATEGORIES_LIST,
                    new TagDependency(['tags' => Cache::TAG_CATEGORIES_LIST])
                )->one()) {
                    $model->{$this->categoriesListAttribute} = [$category->id];
                }
            }
        }
    }
}