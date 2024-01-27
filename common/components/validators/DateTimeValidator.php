<?php namespace common\components\validators;

use Carbon\Carbon;
use common\components\helpers\Api;
use yii\validators\Validator;

class DateTimeValidator extends Validator
{
    public function validateAttribute($model, $attribute)
    {
        try {
            /** @deprecated  */
            if (Api::version(Api::V_2_0, Api::OP_LESS_THAN)) {
                $model->$attribute = Carbon::parse($model->$attribute, 'Europe/Kiev')->setTimezone('UTC');
            }
            else {
                $model->$attribute = Carbon::parse($model->$attribute, 'UTC')->setTimezone('UTC');
            }
        } catch (\Exception $e) {
            $this->addError($model, $attribute, 'Wrong date or time format.');
        }
    }
}