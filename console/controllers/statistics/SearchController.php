<?php namespace console\controllers\statistics;

use common\services\SearchService;
use console\controllers\Controller;

class SearchController extends Controller
{
    private $service;

    public function __construct($id, $module, SearchService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionTopQueries()
    {
        $this->service->cacheAllTopQueries();
    }
}