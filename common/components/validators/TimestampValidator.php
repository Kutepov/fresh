<?php namespace common\components\validators;

use yii\validators\Validator;

class TimestampValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        if (!is_null($model->$attribute) && !preg_match('#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $model->$attribute)) {
            $model->addError($attribute);
        }
    }
}