<?php namespace common\components\validators;

use Ramsey\Uuid\Uuid;
use yii\validators\Validator;

class UUIDValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        if (!Uuid::isValid($model->$attribute)) {
            $this->addError($model, $attribute, 'Wrong id.');
        }
    }
}