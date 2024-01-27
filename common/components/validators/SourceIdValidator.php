<?php namespace common\components\validators;

use common\components\helpers\Api;
use common\services\SourcesService;
use yii\validators\Validator;

class SourceIdValidator extends Validator
{
    public $countryCodeColumn = 'country';
    public $articlesLanguageCodeColumn = 'articlesLanguage';

    /** @var SourcesService */
    private $service;

    public function __construct(SourcesService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($config);
    }

    public function validateAttribute($model, $attribute)
    {
        if (Api::versionLessThan(Api::V_2_20)) {
            $model->$attribute = $this->service->getFilteredSourcesIds(
                (array)$model->$attribute,
                $model->{$this->countryCodeColumn},
                $model->{$this->articlesLanguageCodeColumn}
            );
        }
    }
}