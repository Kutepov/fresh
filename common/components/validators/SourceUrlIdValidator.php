<?php namespace common\components\validators;

use common\components\helpers\Api;
use common\services\SourcesService;
use common\services\SourcesUrlsService;
use yii\validators\Validator;

class SourceUrlIdValidator extends Validator
{
    /** @var SourcesUrlsService */
    private $service;

    public $countryCodeColumn = 'country';
    public $articlesLanguageCodeColumn = 'articlesLanguage';

    public function __construct(SourcesUrlsService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($config);
    }

    public function validateAttribute($model, $attribute)
    {
        if (Api::version(Api::V_2_20)) {
            $model->$attribute = $this->service->getFilteredSourcesUrlsIds(
                (array)$model->$attribute,
                $model->{$this->countryCodeColumn},
                $model->{$this->articlesLanguageCodeColumn}
            );
        }
        else {
            $model->$attribute = [];
        }
    }
}