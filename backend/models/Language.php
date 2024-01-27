<?php namespace backend\models;

use common\components\caching\Cache;
use common\services\MultilingualService;
use Yii;

class Language extends \common\models\Language
{
    /**
     * @var MultilingualService
     */
    public $multilingualService;

    public function __construct($config = [])
    {
        $this->multilingualService = \Yii::$container->get(MultilingualService::class);
        parent::__construct($config);
    }


    public function afterSave($insert, $changedAttributes)
    {
        $this->multilingualService->invalidateCache([Cache::DURATION_LANGUAGES_LIST, 'id', 'name']);
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterDelete()
    {
        $this->multilingualService->invalidateCache([Cache::DURATION_LANGUAGES_LIST, 'id', 'name']);
        parent::afterDelete();
    }

    public static function getListForCountriesForm()
    {
        $newLanguages = [];
        if (!is_null(Yii::$app->request->post('Country')['languages']) && is_array(Yii::$app->request->post('Country')['languages'])) {
            foreach (Yii::$app->request->post('Country')['languages'] as $language) {
                if (!is_numeric($language['id'])) {
                    $newLanguages[$language['id']] = $language['id'];
                }
            }
        }
        $multilingualService = \Yii::$container->get(MultilingualService::class);
        return $multilingualService->getAvailableLanguages('id') + $newLanguages;
    }

    public static function getForDropdown()
    {
        return self::find()->indexBy('code')->select('name')->column();
    }
}