<?php namespace common\components\validators;

use yii\validators\DefaultValueValidator;

class DefaultOnError extends DefaultValueValidator
{
    public $skipOnError = false;

    public function validateAttribute($model, $attribute)
    {
        if ($model->hasErrors($attribute)) {
            $model->$attribute = $this->value;
            $model->clearErrors($attribute);
        }
        else {
            parent::validateAttribute($model, $attribute);
        }
    }
}