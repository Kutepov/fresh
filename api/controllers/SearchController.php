<?php namespace api\controllers;

use api\models\search\ArticlesSearch;
use common\services\SearchService;
use yii\web\Request;

class SearchController extends Controller
{
    private $service;

    public function __construct($id, $module, SearchService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(Request $request): array
    {
        $searchModel = new ArticlesSearch(ArticlesSearch::SCENARIO_SEARCH);
        return $this->service->findArticles(
            $searchModel->loadAndValidate($request->get())
        );
    }

    public function actionTopQueries(Request $request): array
    {
        $searchModel = new ArticlesSearch();
        return $this->service->getTopQueries(
            $searchModel->loadAndValidate($request->get())
        );
    }
}