<?php namespace api\controllers;

use api\models\AdBanner;
use common\models\AdProvider;
use common\models\Advertising;
use yii\db\Expression;
use yii\filters\HttpCache;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Request;

class AdvertisingController extends Controller
{
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => HttpCache::class,
                'only' => ['config', 'get-ad-provider'],
                'cacheControlHeader' => 'public, max-age=86400',
                'etagSeed' => function ($action, $params) {
                    return implode('|', [
                            AdBanner::find()->select(
                                new Expression('MAX(updated_at)'))->scalar(),
                            $this->country,
                            $this->articlesLanguage,
                            $this->language
                        ]
                    );
                },
            ],
        ]);
    }

    /**
     * Список необходимых рекламных банеров
     * @return array
     */
    public function actionConfig(): array
    {
        return AdBanner::getBannersFor($this->currentApp->platform, $this->currentApp->country);
    }

    /**
     * Проверка, включена ли реклама рекреатива на заданной платформе в заданной стране
     * @param Request $request
     * @return bool
     * @throws BadRequestHttpException
     * @deprecated
     */
    public function actionRecreativAdIsEnabled(Request $request)
    {
        if (!$request->get('country')) {
            throw new BadRequestHttpException();
        }

        return Advertising::getIsEnabledFor(AdProvider::RECREATIV, $request->get('platform'), $request->get('country'));
    }

    /**
     * Получение настроек рекламных блоков
     * @param Request $request
     * @return string|null
     * @throws BadRequestHttpException
     * @deprecated
     */
    public function actionGetAdProviderDeprecated(Request $request)
    {
        if (!$request->get('country') || !$request->get('widget')) {
            throw new BadRequestHttpException();
        }

        if ($provider = AdProvider::getProviderFor($request->get('platform'), $request->get('country'), $request->get('widget'))) {
            return $provider->provider;
        }

        return null;
    }

    /**
     * Получение настроек рекламных блоков v2
     * @param Request $request
     * @return string|null
     * @throws BadRequestHttpException
     * @deprecated
     */
    public function actionGetAdProvider(Request $request)
    {
        if (!$request->get('country') || !$request->get('widget')) {
            throw new BadRequestHttpException();
        }

        return AdProvider::getProviderFor($request->get('platform'), $request->get('country'), $request->get('widget'));
    }
}