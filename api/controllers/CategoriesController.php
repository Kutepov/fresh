<?php namespace api\controllers;

use common\components\caching\Cache;
use common\models\Category;
use common\services\CategoriesService;
use common\services\MultilingualService;
use yii\caching\TagDependency;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\web\Request;
use yii\filters\HttpCache;

class CategoriesController extends Controller
{
    /** @var MultilingualService */
    private $multilingualService;
    private $categories;

    public function __construct($id, $module, MultilingualService $multilingualService, CategoriesService $categories, $config = [])
    {
        $this->categories = $categories;
        $this->multilingualService = $multilingualService;
        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        return parent::behaviors();
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => HttpCache::class,
                'only' => ['index'],
                'cacheControlHeader' => 'public, max-age=3600',
                'etagSeed' => function ($action, $params) {
                    return implode('|', [
                        Category::find()->select(new Expression('MAX(updated_at)'))->scalar(),
                            $this->country,
                            $this->articlesLanguage,
                            $this->language,
                        ]
                    );
                },
            ],
        ]);
    }

    /**
     * @param Request $request
     * Список всех категорий для страны / языка
     * @deprecated
     */
    public function actionIndexDeprecated(Request $request)
    {
        if (empty($language = $request->get('language')) || !$this->multilingualService->isSupportedLanguage($language)) {
            $language = $this->multilingualService->getLanguageCodeForCountry($request->get('countryCode'));
        }

        return Category::find()
            ->forCountry($request->get('countryCode'), true)
            ->forLanguage($language)
            ->withoutDefaultCategory()
            ->orderByPriority()
            ->all();
    }

    /**
     * Список всех категорий для платформы / страны / языка
     * @param Request $request
     * @return Category[]
     */
    public function actionIndex(Request $request)
    {
        return $this->categories->getCategoriesList(
            $request->get('countryCode'),
            $request->get('language'),
            $request->get('platform')
        );
    }
}