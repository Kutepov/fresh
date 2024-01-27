<?php namespace common\components\multilingual\behaviors;

use common\services\MultilingualService;

class MultilingualBehavior extends \yeesoft\multilingual\behaviors\MultilingualBehavior
{
    public function __construct(MultilingualService $service, $config = [])
    {
        $config['languages'] = $service->getAvailableLanguages();

        parent::__construct($config);
    }
}