<?php namespace buzz\config\bootstrap;

use common\services\MultilingualService;
use Detection\MobileDetect;
use Google\Cloud\Translate\V2\TranslateClient;
use yii\base\BootstrapInterface;

class Container implements BootstrapInterface
{
    private $service;

    public function __construct(MultilingualService $service)
    {
        $this->service = $service;
    }

    public function bootstrap($app)
    {
//        $this->configureUrlManager($app);
    }

    private function configureUrlManager($app): void
    {
        $availableLanguages = $this->service->getAvailableWebsiteLanguagesForUrlManager();
        $app->urlManager->languages = array_combine($availableLanguages, $availableLanguages);

//        $localizations = $this->service->getAvailableLocalizationsForUrlManager();
//
//        foreach($localizations as $k => $v) {
//            $app->urlManager->geoIpLanguageCountries[$k] = $v;
//        }

        $app->urlManager->hrefLangs = $this->service->getHrefLangs();
    }
}