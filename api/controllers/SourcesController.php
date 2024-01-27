<?php namespace api\controllers;

use common\models\Source;
use common\services\SourcesService;
use yii\db\Expression;
use yii\filters\HttpCache;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Request;

class SourcesController extends Controller
{
    /** @var SourcesService */
    private $service;

    public function __construct($id, $module, SourcesService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        return parent::behaviors();
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => HttpCache::class,
                'only' => ['find'],
                'cacheControlHeader' => 'public, max-age=3600',
                'etagSeed' => function ($action, $params) {
                    return implode('|', [
                            Source::find()->select(new Expression('MAX(updated_at)'))->scalar(),
                            $this->country,
                            $this->articlesLanguage,
                            $this->language
                        ]
                    );
                }
            ]
        ]);
    }

    /**
     * Список всех активных источников для страны
     * @param Request $request
     * @return array|Source[]
     * @throws BadRequestHttpException
     */
    public function actionFind(Request $request): array
    {
        if (!$request->get('countryCode')) {
            throw new BadRequestHttpException();
        }

        return $this->service->getEnabledSources(
            $request->get('countryCode'),
            $this->articlesLanguage
        );
    }
}