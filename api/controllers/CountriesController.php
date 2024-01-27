<?php namespace api\controllers;

use common\components\caching\Cache;
use common\models\Country;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use yii\db\Expression;
use yii\filters\HttpCache;
use yii\web\BadRequestHttpException;

class CountriesController extends Controller
{
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => HttpCache::class,
                'only' => ['index'],
                'cacheControlHeader' => 'public, max-age=86400',
                'etagSeed' => function ($action, $params) {
                    return Country::find()->select(new Expression('MAX(updated_at)'))->scalar();
                }
            ],
        ]);
    }

    /**
     * Список имеющихся стран
     * @return Country[]
     */
    public function actionIndex()
    {
        $query = Country::find()
            ->andWhere(['NOT IN', 'code', ['RU', 'BY']]);

        return $query
            ->cache(
                Cache::DURATION_COUNTRIES_LIST,
                new TagDependency([
                    'tags' => Cache::TAG_COUNTRIES_LIST
                ])
            )
            ->all();
    }

    /**
     * Определение страны по IP
     * @return Country|null
     * @throws BadRequestHttpException
     * @throws \common\exceptions\CountryNotFoundException
     */
    public function actionOfIp()
    {
        if (!($code = geoCountryCode())) {
            throw new BadRequestHttpException();
        }

        if ($code === 'ru' && !$this->ipCondition()) {
            return null;
        }

        return Country::findByCode($code);
    }

    private function ipCondition(): bool
    {
        return \Yii::$app->request->userIP === '94.180.57.104';
    }
}