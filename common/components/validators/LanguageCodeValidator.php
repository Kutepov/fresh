<?php namespace common\components\validators;

use common\services\MultilingualService;
use yii\validators\Validator;

class LanguageCodeValidator extends Validator
{
    private $multilingualService;

    public function __construct(MultilingualService $multilingualService, $config = [])
    {
        $this->multilingualService = $multilingualService;
        parent::__construct($config);
    }

    public function validateAttribute($model, $attribute)
    {
        if (!$this->multilingualService->isSupportedLanguage($model->$attribute)) {
            $this->addError($model, $attribute, 'Not supported language.');
        }
    }
}