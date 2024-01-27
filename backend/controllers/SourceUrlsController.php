<?php namespace backend\controllers;

use backend\models\Country;
use backend\models\forms\ImportSourcesUrls;
use backend\models\Source;
use common\components\scrapers\services\ScrapersService;
use common\models\Category;
use common\services\CategoriesService;
use common\services\feeds\FeedFinderService;
use Yii;
use backend\models\SourceUrl;
use backend\models\search\SourceUrlSearch;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * SourceUrlController implements the CRUD actions for SourceUrl model.
 */
class SourceUrlsController extends BaseController
{
    /**
     * @var ScrapersService
     */
    private $scraperService;
    private $categoriesService;
    private $feedFinder;

    public function __construct($id, $module, ScrapersService $scraperService, CategoriesService $categoriesService, FeedFinderService $feedFinder, $config = [])
    {
        $this->scraperService = $scraperService;
        $this->categoriesService = $categoriesService;
        $this->feedFinder = $feedFinder;
        parent::__construct($id, $module, $config);
    }

    /**
     * @return array[]
     */
    public function behaviors()
    {
        $parent = parent::behaviors();
        return ArrayHelper::merge($parent, [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'edit-category' => ['post'],
                    'get-categories-list' => ['post'],
                    'get-class-list' => ['post'],
                    'get-timezone-list' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionEditCategory()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel(Yii::$app->request->post('editableKey'));
        $model->category_id = Yii::$app->request->post('SourceUrlSearch')[Yii::$app->request->post('editableIndex')]['category_id'];
        if ($model->save(false)) {
            return ['output' => $model->category->title];
        } else {
            return ['message' => implode(', ', $model->getErrorSummary(true))];
        }
    }

    /**
     * Lists all SourceUrl models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SourceUrlSearch;
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }


    /**
     * Process SourceUrl model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @return mixed
     */
    public function actionProcess($id = null)
    {
        $classes = [];
        $categories = [];
        $timezones = [];
        if (is_null($id)) {
            $model = new SourceUrl;
            $model->setDefaults();

            if (Yii::$app->request->referrer) {
                $query = [];
                $parseUrl = parse_url(Yii::$app->request->referrer);
                parse_str($parseUrl['query'], $query);
                if (isset($query['SourceUrlSearch']['source_id'])) {
                    $model->source_id = $query['SourceUrlSearch']['source_id'];
                    $model->countries_ids = $model->source->countries_ids;
                    $categories = $this->prepareCategoryList(
                        $this->getCategoriesList($model->source_id), false
                    );

                    $listClasses = SourceUrl::getListValuesBySourceId($model->source_id, 'class');
                    $classes = $this->prepareClassList(
                        $listClasses ?: $this->getClassList($model->source_id), false
                    );
                    $model->timezone = $model->source->timezone;
                }
            }

        } else {
            $model = $this->findModel($id);
            $classes = $this->prepareClassList(
                $this->scraperService->findAllScrapersClasses(mb_strtolower($model->source->country)),
                false
            );

            if (count($model->source->countries) === 1) {
                $categories = $model->source->countries[0]->getCategories()->multilingual()->all();
            } else {
                $categories = $this->categoriesService->getCategoriesList(null, Yii::$app->language);
            }

            $categories = $this->prepareCategoryList(
                $categories,
                false,
                $model->category
            );
        }

        $timezones = Country::getTimezonesForDropdown();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('process', compact('model', 'classes', 'categories', 'timezones'));
        }
    }

    /**
     * @return array[]
     * @throws NotFoundHttpException
     */
    public function actionGetCategoriesList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (($result = $this->getCategoriesList(Yii::$app->request->post('depdrop_parents')[0]))) {
            return ['output' => $this->prepareCategoryList($result)];
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * @param $source_id
     * @return array
     */
    private function getCategoriesList($source_id)
    {
        if (($model = Source::findOne($source_id)) !== null) {
            if (count($model->countries) === 1) {
                return $model->countries[0]->getCategories()->multilingual()->all();
            }
        }

        return Category::find()->multilingual()->all();
    }

    /**
     * @return array[]
     * @throws NotFoundHttpException
     */
    public function actionGetClassList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $cache = Yii::$app->cache;
        $cacheKey = 'get-class-list';
        $depDropSourceId = Yii::$app->request->post('depdrop_parents')[0];
        $sourceId = Yii::$app->request->post('sourceId');
        $query = Yii::$app->request->post('q');

        if ($depDropSourceId || $sourceId) {
            if (($result = $this->getClassList($sourceId ?: $depDropSourceId))) {
                return [$depDropSourceId ? 'output' : 'results' => $this->prepareClassList($result, true, $depDropSourceId ? ['id', 'name'] : ['id', 'text'])];
            }
        } elseif ($query) {
            $result = $this->getClassList();
            $result = array_search_substring($result, $query);
            return ['results' => $this->prepareClassList($result, true, ['id', 'text'])];
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * @return array
     */
    public function actionGetTimezoneList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $timezones = Country::getTimezonesForDropdown();
        $result = [];
        foreach ($timezones as $timezone) {
            $result[] = [
                'id' => $timezone,
                'name' => $timezone
            ];
        }
        return ['output' => $result];
    }

    private function getClassList($source_id = null)
    {
        $result = [];
        if (($model = Source::findOne($source_id)) !== null) {
            $country = $model->country;
            $result = $this->scraperService->findAllScrapersClasses(mb_strtolower($country));
        }

        if (!count($result)) {
            return $this->scraperService->findAllScrapersClasses();
        }
    }


    /**
     * Deletes an existing SourceUrl model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $ids
     * @return Response
     */
    public function actionDeleteBatch($ids = '')
    {
        if ($ids !== '') {
            SourceUrl::deleteAll(['id' => explode(',', $ids)]);
        }
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @return string|Response
     */
    public function actionImport()
    {
        $model = new ImportSourcesUrls();

        if ($model->load(\Yii::$app->request->post()) && $model->validate()) {
            $model->save();
            return $this->redirect(Yii::$app->request->referrer);
        }

        return $this->renderAjax('form-import', compact('model'));
    }


    /**
     * @param string $ids
     * @param int $enable
     * @return Response
     * @throws \yii\db\Exception
     */
    public function actionChangeStatusBatch($ids = '', $enable = 1)
    {
        if ($ids !== '') {
            Yii::$app->db->createCommand()
                ->update(SourceUrl::tableName(),
                    ['enabled' => $enable],
                    ['id' => explode(',', $ids)])
                ->execute();
        }
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Finds the SourceUrl model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SourceUrl the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SourceUrl::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * @param $classes
     * @param bool $forAjax
     * @return array
     */
    private function prepareClassList($classes, $forAjax = true, $arrayKeys = ['id', 'name']): array
    {
        $result = [];
        foreach ($classes as $class) {
            $shortName = str_replace($this->scraperService::PATH_TO_SOURCES . '\\', '', $class);
            if ($forAjax) {
                $result[] = [$arrayKeys[0] => $class, $arrayKeys[1] => $shortName];
            } else {
                $result[$class] = $shortName;
            }

        }

        $universalScrappers = $this->feedFinder->getAvailableScrapers();
        $universalScrappersPrepared = [];

        foreach ($universalScrappers as $universalScrapper) {
            $title = @end(@explode('\\', $universalScrapper));

            if ($forAjax) {
                $universalScrappersPrepared[] = [$arrayKeys[0] => $universalScrapper, $arrayKeys[1] => $title];
            } else {
                $universalScrappersPrepared[$universalScrapper] = $title;
            }
        }

        $result = [
            [
                'text' => 'Универсальные',
                'children' => $universalScrappersPrepared
            ],
            [
                'text' => 'Остальные',
                'children' => $result
            ]
        ];
        return $result;
    }

    /**
     * @param $categories
     * @param bool $forAjax
     * @return array
     */
    private function prepareCategoryList($categories, $forAjax = true, $defaultCategory = null): array
    {
        $result = [];

        if ($defaultCategory) {
            if ($forAjax) {
                $result[] = ['id' => $defaultCategory->id, 'name' => $defaultCategory->title];
            } else {
                $result[$defaultCategory->id] = $defaultCategory->title;
            }
        }

        foreach ($categories as $category) {
            if ($forAjax) {
                $result[] = ['id' => $category->id, 'name' => $category->title];
            } else {
                $result[$category->id] = $category->title;
            }
        }

        return $result;
    }
}
